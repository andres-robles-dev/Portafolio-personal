import process from "node:process";

function normalizeBaseUrl(value) {
    try {
        const url = new URL(value);
        return url.origin;
    } catch {
        return null;
    }
}

async function request(url, options = {}) {
    const response = await fetch(url, options);
    const contentType = response.headers.get("content-type") || "";
    const payload = contentType.includes("application/json")
        ? await response.json().catch(() => null)
        : await response.text().catch(() => "");

    return {
        ok: response.ok,
        status: response.status,
        payload,
        headers: Object.fromEntries(response.headers.entries())
    };
}

function logCheck(label, passed, detail) {
    const icon = passed ? "OK" : "FAIL";
    console.log(`[${icon}] ${label}${detail ? ` -> ${detail}` : ""}`);
}

function hasCacheDirective(headers, directive) {
    const cacheControl = String(headers["cache-control"] || "").toLowerCase();
    return cacheControl.includes(directive.toLowerCase());
}

function extractReleaseIds(htmlText) {
    const text = String(htmlText || "");
    const pattern = /assets\/(?:css\/(?:main|assistant)\.css|js\/(?:main|assistant|static-nav)\.js)\?v=([0-9]{8}-[0-9]{6})/g;
    const ids = [];
    let match = null;

    while ((match = pattern.exec(text)) !== null) {
        ids.push(match[1]);
    }

    return ids;
}

async function main() {
    const [, , baseUrlArg] = process.argv;
    const healthToken = process.env.HEALTHCHECK_TOKEN || "";
    const exercise = process.argv.includes('--exercise');

    if (!baseUrlArg) {
        console.error("Uso: node scripts/verify-production.mjs https://tu-dominio.com [--exercise]. Token solo en entorno.");
        process.exit(1);
    }

    const baseUrl = normalizeBaseUrl(baseUrlArg);

    if (!baseUrl) {
        console.error("URL invalida. Ejemplo: https://tu-dominio.com");
        process.exit(1);
    }

    const checks = [];
    const baseHost = new URL(baseUrl).host;
    const local = ['localhost', '127.0.0.1', '[::1]'].includes(new URL(baseUrl).hostname);
    if (!baseUrl.startsWith('https://') && !local) throw new Error('Se requiere HTTPS fuera de localhost.');

    const dotEnvProbe = await request(`${baseUrl}/api/.env`);
    const dotEnvBlocked = dotEnvProbe.status === 403 || dotEnvProbe.status === 404;
    checks.push(dotEnvBlocked);
    logCheck("Bloqueo de /api/.env", dotEnvBlocked, `HTTP ${dotEnvProbe.status}`);
    for (const path of ['/.git/config', '/storage/contacts.ndjson', '/api/bootstrap.php', '/local-server.mjs']) {
        const result = await request(`${baseUrl}${path}`);
        const blocked = [403, 404].includes(result.status);
        checks.push(blocked);
        logCheck(`Bloqueo de ${path}`, blocked, `HTTP ${result.status}`);
    }

    const chatGet = await request(`${baseUrl}/api/chat.php`);
    const chatMethodGuard = chatGet.status === 405;
    checks.push(chatMethodGuard);
    logCheck("Metodo restringido en /api/chat.php", chatMethodGuard, `HTTP ${chatGet.status}`);

    const xPoweredBy = String(chatGet.headers["x-powered-by"] || "").trim();
    const xPoweredByHidden = xPoweredBy === "";
    checks.push(xPoweredByHidden);
    logCheck("Ocultar X-Powered-By", xPoweredByHidden, xPoweredBy ? `Valor: ${xPoweredBy}` : "Sin cabecera");

    const contactGet = await request(`${baseUrl}/api/contact.php`);
    const contactMethodGuard = contactGet.status === 405;
    checks.push(contactMethodGuard);
    logCheck("Metodo restringido en /api/contact.php", contactMethodGuard, `HTTP ${contactGet.status}`);

    const chatAliasGet = await request(`${baseUrl}/api/chat`);
    const chatAliasOk = chatAliasGet.status === 405;
    checks.push(chatAliasOk);
    logCheck("Alias /api/chat via .htaccess", chatAliasOk, `HTTP ${chatAliasGet.status}`);

    const contactAliasGet = await request(`${baseUrl}/api/contact`);
    const contactAliasOk = contactAliasGet.status === 405;
    checks.push(contactAliasOk);
    logCheck("Alias /api/contact via .htaccess", contactAliasOk, `HTTP ${contactAliasGet.status}`);

    const indexPage = await request(`${baseUrl}/index.html`);
    const indexOk = indexPage.status === 200 && typeof indexPage.payload === "string";
    checks.push(indexOk);
    logCheck("GET /index.html", indexOk, `HTTP ${indexPage.status}`);

    const assistantPage = await request(`${baseUrl}/asistente.html`);
    const assistantOk = assistantPage.status === 200 && typeof assistantPage.payload === "string";
    checks.push(assistantOk);
    logCheck("GET /asistente.html", assistantOk, `HTTP ${assistantPage.status}`);

    const indexNoStore = hasCacheDirective(indexPage.headers, "no-store");
    checks.push(indexNoStore);
    logCheck("Cache-Control index no-store", indexNoStore, indexPage.headers["cache-control"] || "sin cabecera");

    const assistantNoStore = hasCacheDirective(assistantPage.headers, "no-store");
    checks.push(assistantNoStore);
    logCheck("Cache-Control asistente no-store", assistantNoStore, assistantPage.headers["cache-control"] || "sin cabecera");

    const indexReleaseIds = extractReleaseIds(indexPage.payload);
    const assistantReleaseIds = extractReleaseIds(assistantPage.payload);
    const uniqueIndexReleaseIds = Array.from(new Set(indexReleaseIds));
    const uniqueAssistantReleaseIds = Array.from(new Set(assistantReleaseIds));
    const releaseId = uniqueIndexReleaseIds[0] || "";

    const releaseConsistent = uniqueIndexReleaseIds.length === 1
        && uniqueAssistantReleaseIds.length === 1
        && releaseId !== ""
        && uniqueAssistantReleaseIds[0] === releaseId;
    checks.push(releaseConsistent);
    logCheck(
        "Version unificada entre index y asistente",
        releaseConsistent,
        releaseConsistent
            ? `release=${releaseId}`
            : `index=${uniqueIndexReleaseIds.join(",") || "none"}, asistente=${uniqueAssistantReleaseIds.join(",") || "none"}`
    );

    if (exercise) {
    console.log('[AVISO] --exercise realiza POST: puede consumir cuota y almacenar solicitudes o enviar correo en modo real.');
    const chatPost = await request(`${baseUrl}/api/chat.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            sessionId: `sess_verify_${Date.now()}`,
            message: "Hola, quiero validar que el endpoint de chat esta operativo y orientado a cotizacion.",
            context: {
                source: "qa-script",
                page: "assistant"
            },
            history: []
        })
    });

    const chatPostOk = chatPost.status === 200
        && chatPost.payload
        && typeof chatPost.payload.reply === "string"
        && chatPost.payload.reply.trim().length > 0
        && typeof chatPost.payload.leadStatus === "string"
        && typeof chatPost.payload.source === "string"
        && typeof chatPost.payload.readinessScore === "number"
        && typeof chatPost.payload.nextBestAction === "string"
        && typeof chatPost.payload.canSendQuote === "boolean"
        && typeof chatPost.payload.scopeStatus === "string";
    checks.push(chatPostOk);
    logCheck("POST /api/chat.php", chatPostOk, `HTTP ${chatPost.status}`);

    const randomEmail = `qa.${Date.now()}@example.com`;
    const contactPost = await request(`${baseUrl}/api/contact.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            name: "QA Verificacion",
            email: randomEmail,
            company: "Test Corp",
            inquiryType: "producto",
            message: "Necesito validar que el endpoint de contacto almacena correctamente esta solicitud de prueba.",
            source: "qa-script",
            formStartedAt: Date.now() - 8000,
            submittedAt: Date.now(),
            createdAt: new Date().toISOString()
        })
    });

    const contactPostOk = contactPost.status === 200 && contactPost.payload && contactPost.payload.status === "ok";
    checks.push(contactPostOk);
    logCheck("POST /api/contact.php", contactPostOk, `HTTP ${contactPost.status}`);
    } else {
        console.log('[SKIP] POST de chat/contacto: requiere --exercise para autorizar sus efectos.');
    }

    if (healthToken) {
        const health = await request(`${baseUrl}/api/health.php`, { headers: { 'X-Health-Token': healthToken } });
        const healthOk = health.status === 200 && health.payload && health.payload.ok === true;
        checks.push(healthOk);
        logCheck("GET /api/health.php", healthOk, `HTTP ${health.status}`);
    } else {
        console.log("[SKIP] GET /api/health.php -> sin HEALTHCHECK_TOKEN en entorno");
    }

    if (local) {
        console.log('[SKIP] HTTPS y redireccion: servidor local.');
    } else if (!baseUrl.startsWith("https://")) {
        checks.push(false);
        logCheck("Uso de HTTPS", false, "La URL base no usa https://");
    } else {
        checks.push(true);
        logCheck("Uso de HTTPS", true);

        const httpRedirectProbe = await request(`http://${baseHost}/api/chat.php`, {
            redirect: "manual"
        });
        const redirectedToHttps = httpRedirectProbe.status >= 300 && httpRedirectProbe.status < 400;
        checks.push(redirectedToHttps);
        logCheck("Redireccion HTTP -> HTTPS", redirectedToHttps, `HTTP ${httpRedirectProbe.status}`);
    }

    const failed = checks.filter((value) => !value).length;

    if (failed > 0) {
        console.error(`\nVerificacion completada con ${failed} fallo(s).`);
        process.exit(1);
    }

    console.log("\nVerificacion completada sin fallos.");
}

main().catch((error) => {
    console.error("Error ejecutando verificacion:", error?.message || error);
    process.exit(1);
});
