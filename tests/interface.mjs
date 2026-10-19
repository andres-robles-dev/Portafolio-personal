import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import * as fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import net from 'node:net';
import { setTimeout as wait } from 'node:timers/promises';

const root = path.resolve(import.meta.dirname, '..');
async function until(action) {
    for (let n = 0; n < 160; n++) {
        try { const result = await action(); if (result) return result; } catch { /* Esperar al proceso de prueba. */ }
        await wait(50);
    }
    throw new Error('Tiempo agotado en la prueba visual.');
}
async function stop(child) {
    if (child && child.exitCode === null && child.signalCode === null) await new Promise(resolve => { child.once('exit', resolve); child.kill(); });
}

async function main() {
    const candidates = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome'];
    let executable;
    for (const candidate of candidates.filter(Boolean)) {
        try { await fs.access(candidate); executable = candidate; break; } catch { /* Otro navegador. */ }
    }
    if (!executable) throw new Error('Configura CHROME_PATH o instala Chrome/Chromium.');
    const baseTmp = process.platform === 'win32' ? path.join(process.env.LOCALAPPDATA, 'Temp/opencode') : os.tmpdir();
    const tmp = await fs.mkdtemp(path.join(baseTmp, 'portfolio-ui-'));
    let php, chrome, socket;
    try {
        const probe = net.createServer();
        await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
        const port = probe.address().port;
        await new Promise(resolve => probe.close(resolve));
        const base = `http://127.0.0.1:${port}`;
        php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', '.', 'router.php'], {
            cwd: root, stdio: 'ignore', windowsHide: true,
            env: { ...process.env, APP_LOAD_ENV: '0', APP_MODE: 'demo', DEEPSEEK_API_KEY: '', CONTACT_NOTIFY_EMAIL: '', CHAT_QUOTE_NOTIFY_EMAIL: '',
                RATE_LIMIT_DIR: path.join(tmp, 'rates'), CHAT_V2_MEMORY_PATH: path.join(tmp, 'memory'),
                CONTACT_STORAGE_PATH: path.join(tmp, 'contacts.ndjson'), CHAT_QUOTE_STORAGE_PATH: path.join(tmp, 'quotes.ndjson') },
        });
        await until(async () => (await fetch(base)).ok);
        chrome = spawn(executable, ['--headless=new', '--remote-debugging-port=0', '--remote-debugging-address=127.0.0.1',
            `--user-data-dir=${path.join(tmp, 'browser')}`, '--disable-extensions', '--disable-background-networking', '--no-first-run', 'about:blank'],
        { stdio: 'ignore', windowsHide: true });
        const debugPort = await until(async () => Number((await fs.readFile(path.join(tmp, 'browser/DevToolsActivePort'), 'utf8')).split('\n')[0]));
        const pages = await (await fetch(`http://127.0.0.1:${debugPort}/json/list`)).json();
        socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
        let id = 0;
        const pending = new Map();
        const runtimeErrors = [];
        socket.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.method === 'Runtime.exceptionThrown') runtimeErrors.push('Excepcion de interfaz');
            const task = pending.get(data.id);
            if (!task) return;
            pending.delete(data.id);
            data.error ? task.reject(new Error(data.error.message)) : task.resolve(data.result);
        });
        const call = (method, params = {}) => new Promise((resolve, reject) => {
            pending.set(++id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params }));
        });
        const evaluate = async expression => {
            const result = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
            return result.result.value;
        };
        await call('Page.enable');
        await call('Runtime.enable');
        await call('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
        await call('Page.navigate', { url: base });
        await until(() => evaluate('!!document.querySelector(".menu-toggle[aria-controls]")'));
        assert.match(await evaluate('document.querySelector("h1").textContent'), /JUNIOR/);
        assert.equal(await evaluate('document.querySelectorAll(".center-cover, #projectsSection, #projectModal, .social-links").length'), 0);
        assert.equal(await evaluate('document.querySelectorAll("img").length'), 0);
        for (const tech of ['HTML', 'CSS', 'JavaScript', 'Bootstrap', 'React', 'Tailwind CSS', 'Java', 'PHP', 'Spring Boot', 'Spring Security', 'APIs REST', 'PostgreSQL', 'MySQL']) {
            assert.ok(await evaluate(`document.querySelector('.hero-skill-grid').textContent.includes(${JSON.stringify(tech)})`), tech);
        }
        await evaluate('document.querySelector(".main-nav [data-nav=about]").click()');
        await wait(1800);
        assert.ok(await evaluate('document.getElementById("aboutSection").classList.contains("active")'));
        await evaluate('document.querySelector(".main-nav [data-nav=contact]").click(); document.getElementById("contactForm").requestSubmit();');
        assert.ok(await evaluate('document.querySelectorAll("#contactForm [aria-invalid=true]").length > 0'));
        await evaluate(`var form=document.getElementById('contactForm'); form.elements.name.value='QA Demo'; form.elements.email.value='qa@example.com';
            form.elements.inquiryType.value='aprendizaje'; form.elements.message.value='Ejemplo ficticio para comprobar una web de practica.'; form.requestSubmit();`);
        await until(() => evaluate('document.getElementById("contactFeedback").textContent.includes("Demostracion completada")'));
        assert.equal(await evaluate('localStorage.getItem("portfolio_contact_queue_v1")'), null);
        assert.equal(await evaluate('performance.getEntriesByType("resource").filter(r => r.name.includes("/api/")).length'), 0);

        await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await evaluate('document.querySelector(".menu-toggle").click()');
        assert.equal(await evaluate('document.getElementById("navPanel").getAttribute("aria-hidden")'), 'false');
        await evaluate('document.querySelector("#navPanel [data-nav=home]").click()');
        assert.equal(await evaluate('document.getElementById("navPanel").getAttribute("aria-hidden")'), 'true');

        await call('Page.navigate', { url: `${base}/asistente.html` });
        await until(() => evaluate('!!document.getElementById("assistantChatInput") && document.getElementById("assistantChatInput").placeholder.includes("prueba")'));
        await evaluate('document.getElementById("assistantChatInput").value="Caso ficticio para aprender"; document.getElementById("assistantChatForm").requestSubmit()');
        await until(() => evaluate('document.getElementById("assistantChatMessages").textContent.includes("programador junior")'));
        assert.equal(await evaluate('performance.getEntriesByType("resource").filter(r => r.name.includes("/api/")).length'), 0);
        assert.equal(await evaluate('document.querySelectorAll("#assistantChatMessages img").length'), 0);
        assert.equal(await evaluate('localStorage.getItem("portfolio_chat_session_v1")'), null);
        assert.equal(await fs.stat(path.join(tmp, 'contacts.ndjson')).then(() => true, () => false), false);
        assert.equal(await fs.stat(path.join(tmp, 'quotes.ndjson')).then(() => true, () => false), false);
        assert.equal(runtimeErrors.length, 0);
        console.log('OK: perfil junior, stack completo, sin foto/proyectos/redes, navegacion y menu movil, formulario y chat locales sin llamadas API.');
    } finally {
        socket?.close();
        await stop(chrome);
        await stop(php);
        await wait(300);
        await fs.rm(tmp, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
