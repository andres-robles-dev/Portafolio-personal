import {
    CONTACT_API_ENDPOINT,
    CONTACT_API_TIMEOUT_MS,
    CONTACT_QUEUE_STORAGE_KEY
} from "../core/constants.js";

function safeLoadQueue() {
    try {
        const raw = localStorage.getItem(CONTACT_QUEUE_STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function safeSaveQueue(queue) {
    try {
        localStorage.setItem(CONTACT_QUEUE_STORAGE_KEY, JSON.stringify(queue));
    } catch {
        return;
    }
}

async function postContactPayload(payload, endpoint, timeoutMs) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload),
            signal: controller.signal
        });

        if (!response.ok) {
            throw new Error(`HTTP_${response.status}`);
        }

        return true;
    } finally {
        clearTimeout(timer);
    }
}

async function flushQueue(endpoint, timeoutMs) {
    const queue = safeLoadQueue();

    if (!queue.length) {
        return 0;
    }

    const pending = [];

    for (const item of queue) {
        try {
            await postContactPayload(item, endpoint, timeoutMs);
        } catch {
            pending.push(item);
        }
    }

    safeSaveQueue(pending);
    return pending.length;
}

export async function submitContact(payload, options = {}) {
    const endpoint = options.endpoint ?? CONTACT_API_ENDPOINT;
    const timeoutMs = options.timeoutMs ?? CONTACT_API_TIMEOUT_MS;

    await flushQueue(endpoint, timeoutMs);

    try {
        await postContactPayload(payload, endpoint, timeoutMs);
        return {
            status: "sent"
        };
    } catch {
        const queue = safeLoadQueue();
        queue.push(payload);
        safeSaveQueue(queue);

        return {
            status: "queued",
            queuedItems: queue.length
        };
    }
}

export function getQueuedContactsCount() {
    return safeLoadQueue().length;
}

export async function syncQueuedContacts(options = {}) {
    const endpoint = options.endpoint ?? CONTACT_API_ENDPOINT;
    const timeoutMs = options.timeoutMs ?? CONTACT_API_TIMEOUT_MS;
    return flushQueue(endpoint, timeoutMs);
}
