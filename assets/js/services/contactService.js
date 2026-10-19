import { CONTACT_API_ENDPOINT, CONTACT_API_TIMEOUT_MS, CONTACT_QUEUE_STORAGE_KEY } from '../core/constants.js';

export function getQueuedContactsCount() {
    try {
        const raw = localStorage.getItem(CONTACT_QUEUE_STORAGE_KEY);
        if (!raw) return 0;
        if (raw.length > 1000000) return 1;
        const queue = JSON.parse(raw);
        return Array.isArray(queue) ? queue.length : 0;
    } catch {
        return 0;
    }
}

export function clearQueuedContacts() {
    try {
        localStorage.removeItem(CONTACT_QUEUE_STORAGE_KEY);
        return true;
    } catch {
        return false;
    }
}

export async function submitContact(payload, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), options.timeoutMs ?? CONTACT_API_TIMEOUT_MS);
    try {
        const response = await fetch(options.endpoint ?? CONTACT_API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            signal: controller.signal,
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => null);
        if (response.ok && data?.status === 'demo' && data.stored === false) return { status: 'demo' };
        if (response.ok && data?.status === 'ok' && data.stored === true) return { status: 'sent' };
        return {
            status: 'error',
            message: response.status === 429 ? 'Se alcanzo un limite de uso. Espera antes de reintentar.' : 'La solicitud no fue aceptada. Revisa los campos e intentalo de nuevo.',
            fields: data?.fields && typeof data.fields === 'object' ? data.fields : {},
        };
    } catch {
        return { status: 'error', message: 'No se pudo enviar. Tus datos permanecen en el formulario, sin guardarse en el navegador.' };
    } finally {
        clearTimeout(timer);
    }
}

export async function syncQueuedContacts() {
    return getQueuedContactsCount();
}
