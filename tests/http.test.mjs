import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import * as fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import net from 'node:net';
import { setTimeout as wait } from 'node:timers/promises';

const root = path.resolve(import.meta.dirname, '..');
const run = promisify(execFile);
const tmpBase = process.platform === 'win32' ? path.join(process.env.LOCALAPPDATA, 'Temp/opencode') : os.tmpdir();

async function start(t, env = {}, staticOnly = false) {
    const tmp = await fs.mkdtemp(path.join(tmpBase, 'portfolio-http-'));
    const probe = net.createServer();
    await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
    const port = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    const token = `test_${path.basename(tmp)}`;
    const child = spawn(staticOnly ? process.execPath : 'php', staticOnly ? ['local-server.mjs'] : ['-S', `127.0.0.1:${port}`, '-t', '.', 'router.php'], {
        cwd: root, stdio: 'ignore', windowsHide: true,
        env: { ...process.env, APP_LOAD_ENV: '0', APP_MODE: 'demo', DEEPSEEK_API_KEY: '', CONTACT_NOTIFY_EMAIL: '', CHAT_QUOTE_NOTIFY_EMAIL: '',
            RATE_LIMIT_DIR: path.join(tmp, 'rates'), CONTACT_STORAGE_PATH: path.join(tmp, 'contacts.ndjson'),
            CHAT_V2_MEMORY_PATH: path.join(tmp, 'memory'), CHAT_QUOTE_STORAGE_PATH: path.join(tmp, 'quotes.ndjson'),
            HEALTHCHECK_TOKEN: token, CHAT_RATE_LIMIT_MAX: '100', CONTACT_RATE_LIMIT_MAX: '100', TRUSTED_PROXIES: '', PORT: String(port), ...env },
    });
    let error;
    child.on('error', value => { error = value; });
    t.after(async () => {
        if (child.exitCode === null && child.signalCode === null) await new Promise(resolve => { child.once('exit', resolve); child.kill(); });
        await fs.rm(tmp, { recursive: true, force: true });
    });
    const base = `http://127.0.0.1:${port}`;
    for (let n = 0; n < 80; n++) {
        if (error) throw error;
        try { await fetch(base); return { base, tmp, token }; } catch { await wait(50); }
    }
    throw new Error('Servidor de prueba no disponible');
}

const contact = { name: 'QA Demo', email: 'qa@example.com', inquiryType: 'producto', message: 'Ejemplo ficticio de un proyecto para aprender programacion.' };
const chat = { message: 'Caso ficticio', sessionId: 'sess_public_demo', history: [], context: {} };
const post = (base, endpoint, body, headers = {}) => fetch(base + endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(body) });

test('PHP y Node bloquean privados, codigo backend y traversal sin bloquear imagenes validas', async t => {
    for (const staticOnly of [false, true]) {
        const { base } = await start(t, {}, staticOnly);
        assert.equal((await fetch(base)).status, 200);
        assert.equal((await fetch(base + '/img/Proyecto%20nuevo%20(7).png')).status, 200);
        for (const url of ['/api/.env', '/.git/config', '/storage/contacts.ndjson', '/api/bootstrap.php', '/api/knowledge/motor.json', '/local-server.mjs', '/tests/security.php', '/%2eenv', '/img/%00.jpg']) {
            assert.equal((await fetch(base + url)).status, 404, url);
        }
    }
});

test('API valida metodos, tipo, tamano y origen; demo no guarda datos ni envia solicitudes', async t => {
    const { base, tmp, token } = await start(t);
    assert.equal((await fetch(base + '/api/chat')).status, 405);
    assert.equal((await fetch(base + '/api/contact.php')).status, 405);
    assert.equal((await post(base, '/api/chat.php', chat, { Origin: 'https://example.com' })).status, 403);
    assert.equal((await post(base, '/api/contact.php', contact, { 'Content-Type': 'text/plain' })).status, 415);
    assert.equal((await post(base, '/api/contact.php', { ...contact, name: [] })).status, 422);
    assert.equal((await post(base, '/api/chat.php', { ...chat, message: [] })).status, 422);
    assert.equal((await post(base, '/api/chat.php', { ...chat, message: 'x'.repeat(65537) })).status, 413);
    const result = await (await post(base, '/api/contact.php', contact)).json();
    assert.equal(result.status, 'demo');
    assert.equal(result.stored, false);
    assert.equal(await fs.stat(path.join(tmp, 'contacts.ndjson')).then(() => true, () => false), false);
    assert.equal((await fetch(base + `/api/health.php?token=${token}`)).status, 403);
    const health = await (await fetch(base + '/api/health.php', { headers: { 'X-Health-Token': token } })).json();
    assert.equal(health.mode, 'demo');
    assert.equal(JSON.stringify(health).includes(tmp), false);
    assert.equal(await fs.stat(path.join(tmp, '.write_probe')).then(() => true, () => false), false);
});

test('cookie de propiedad aisla sesiones aunque otro cliente copie el ID publico', async t => {
    const { base, tmp } = await start(t);
    const first = await post(base, '/api/chat.php', chat);
    const cookie = first.headers.get('set-cookie');
    assert.match(cookie, /HttpOnly/i);
    assert.match(cookie, /SameSite=Strict/i);
    const data = await first.json();
    assert.equal(data.source, 'demo');
    const other = await (await post(base, '/api/chat.php', { ...chat, sessionId: data.sessionId })).json();
    assert.notEqual(other.sessionId, data.sessionId);
    const own = await (await post(base, '/api/chat.php', { ...chat, sessionId: other.sessionId }, { Cookie: cookie.split(';')[0] })).json();
    assert.equal(own.sessionId, data.sessionId);
    assert.equal(await fs.stat(path.join(tmp, 'memory')).then(() => true, () => false), false);
});

test('X-Forwarded-For falso no permite saltarse la cuota por IP', async t => {
    const { base } = await start(t, { CHAT_RATE_LIMIT_MAX: '2' });
    for (let n = 0; n < 2; n++) assert.equal((await post(base, '/api/chat.php', chat, { 'X-Forwarded-For': `192.0.2.${n + 1}` })).status, 200);
    assert.equal((await post(base, '/api/chat.php', chat, { 'X-Forwarded-For': '192.0.2.55' })).status, 429);
});

test('modo real conserva contactos aceptados en almacenamiento aislado sin enviar correo', async t => {
    const { base, tmp } = await start(t, { APP_MODE: 'live' });
    const result = await (await post(base, '/api/contact.php', contact)).json();
    assert.equal(result.stored, true);
    assert.equal(result.notified, false);
    const stored = JSON.parse((await fs.readFile(path.join(tmp, 'contacts.ndjson'), 'utf8')).trim());
    assert.equal(stored.email, 'qa@example.com');
    const missingKey = await (await post(base, '/api/chat.php', chat)).json();
    assert.equal(missingKey.error.code, 'MISSING_API_KEY');
});

test('contador atomico admite exactamente cinco solicitudes concurrentes', async t => {
    const tmp = await fs.mkdtemp(path.join(tmpBase, 'portfolio-rate-'));
    t.after(() => fs.rm(tmp, { recursive: true, force: true }));
    const results = await Promise.all(Array.from({ length: 15 }, () => run('php', ['tests/helpers/rate-worker.php'], {
        cwd: root, env: { ...process.env, APP_LOAD_ENV: '0', RATE_LIMIT_DIR: tmp }, windowsHide: true,
    })));
    assert.equal(results.filter(r => r.stdout === '1').length, 5);
});

test('cuotas globales limitan tanto chat como formulario', async t => {
    const { base } = await start(t, { CHAT_GLOBAL_DAILY_MAX: '1', CONTACT_GLOBAL_HOURLY_MAX: '1' });
    assert.equal((await post(base, '/api/chat.php', chat)).status, 200);
    const limitedChat = await post(base, '/api/chat.php', chat);
    assert.equal(limitedChat.status, 429);
    assert.equal((await limitedChat.json()).error.code, 'RATE_LIMIT_GLOBAL');
    assert.equal((await post(base, '/api/contact.php', contact)).status, 200);
    assert.equal((await post(base, '/api/contact.php', contact)).status, 429);
});

test('cliente IA diferencia TLS, timeout, credenciales y cuota sin exponer detalles', async () => {
    for (const [scenario, code] of [['tls', 'UPSTREAM_TLS_ERROR'], ['timeout', 'UPSTREAM_TIMEOUT'], ['auth', 'UPSTREAM_AUTH_ERROR'],
        ['quota', 'UPSTREAM_RATE_LIMIT'], ['invalid', 'EMPTY_UPSTREAM_REPLY']]) {
        const result = await run('php', ['tests/helpers/model-worker.php', scenario], { cwd: root, env: { ...process.env, APP_LOAD_ENV: '0' }, windowsHide: true });
        assert.equal(JSON.parse(result.stdout).error.code, code);
        assert.doesNotMatch(result.stdout, /detalle interno oculto|test_placeholder/);
    }
});
