import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolvePublicPath } from '../scripts/public-paths.mjs';
import { submitContact, getQueuedContactsCount, syncQueuedContacts, clearQueuedContacts } from '../assets/js/services/contactService.js';
import { requestChatReply } from '../assets/js/services/chatService.js';

test('servidor estatico solo resuelve archivos publicos y admite imagenes con parentesis', async () => {
    const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
    assert.ok(await resolvePublicPath(decodeURIComponent(root), '/img/Proyecto%20nuevo%20(7).png'));
    for (const path of ['/api/.env', '/.git/config', '/api/bootstrap.php', '/storage/contacts.ndjson', '/api/knowledge/motor.json', '/scripts/release-version.mjs', '/img/../api/.env']) {
        assert.equal(await resolvePublicPath(decodeURIComponent(root), path), null, path);
    }
});

test('contacto no persiste ni reenvia datos, y distingue aceptacion, demo y errores', async () => {
    let calls = 0;
    let writes = 0;
    const old = JSON.stringify([{ name: 'QA', email: 'qa@example.com' }]);
    globalThis.localStorage = { getItem: () => old, setItem: () => { writes++; }, removeItem: () => { writes++; } };
    globalThis.fetch = async () => { calls++; throw new Error('red simulada'); };
    assert.equal(getQueuedContactsCount(), 1);
    assert.equal(await syncQueuedContacts(), 1);
    assert.equal(calls, 0);
    const result = await submitContact({ name: 'QA', email: 'qa@example.com' });
    assert.equal(result.status, 'error');
    assert.equal(writes, 0);
    assert.equal(calls, 1);
    for (const [body, expected] of [[{ status: 'ok', stored: true }, 'sent'], [{ status: 'ok', stored: false, screened: true }, 'error'], [{ status: 'demo', stored: false }, 'demo']]) {
        globalThis.fetch = async () => ({ ok: true, json: async () => body });
        assert.equal((await submitContact({})).status, expected);
    }
    globalThis.fetch = async () => ({ ok: false, status: 422, json: async () => ({ fields: { name: 'Revisar' } }) });
    assert.equal((await submitContact({})).fields.name, 'Revisar');
    assert.equal(writes, 0);
    assert.equal(clearQueuedContacts(), true);
    assert.equal(writes, 1);
});

test('chat utiliza cookie same-origin y no lee ni escribe el sessionId antiguo', async () => {
    globalThis.localStorage = { getItem: () => { throw new Error('No leer'); }, setItem: () => { throw new Error('No escribir'); } };
    let options;
    globalThis.fetch = async (url, input) => {
        options = input;
        return { ok: true, text: async () => JSON.stringify({ reply: 'Respuesta simulada', sessionId: 'a'.repeat(64), source: 'demo' }) };
    };
    const result = await requestChatReply('Caso ficticio');
    assert.equal(result.reply, 'Respuesta simulada');
    assert.equal(options.credentials, 'same-origin');
    globalThis.fetch = async () => { throw new Error('red'); };
    assert.equal((await requestChatReply('Otra consulta')).source, 'fallback');
});
