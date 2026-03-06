import {
    CHAT_API_ENDPOINT,
    CHAT_API_TIMEOUT_MS,
    CHAT_SESSION_STORAGE_KEY
} from "../core/constants.js";

function createSessionId() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `sess_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function getSessionId() {
    try {
        const existing = localStorage.getItem(CHAT_SESSION_STORAGE_KEY);

        if (existing) {
            return existing;
        }

        const created = createSessionId();
        localStorage.setItem(CHAT_SESSION_STORAGE_KEY, created);
        return created;
    } catch {
        return createSessionId();
    }
}

function createLeadShape(overrides = {}) {
    const contact = overrides.contact && typeof overrides.contact === "object"
        ? overrides.contact
        : {};

    return {
        projectType: String(overrides.projectType || "").trim(),
        requestedPlatform: String(overrides.requestedPlatform || "").trim(),
        stackPreference: String(overrides.stackPreference || "").trim(),
        collaborationMode: String(overrides.collaborationMode || "").trim(),
        problem: String(overrides.problem || "").trim(),
        objective: String(overrides.objective || "").trim(),
        timeline: String(overrides.timeline || "").trim(),
        scopeNotes: String(overrides.scopeNotes || "").trim(),
        budgetHint: String(overrides.budgetHint || "").trim(),
        urgency: String(overrides.urgency || "").trim(),
        contact: {
            name: String(contact.name || "").trim(),
            email: String(contact.email || "").trim(),
            phone: String(contact.phone || "").trim(),
            status: String(contact.status || "not_requested").trim() || "not_requested"
        }
    };
}

function createResponse(overrides = {}) {
    const lead = overrides.lead && typeof overrides.lead === "object"
        ? createLeadShape(overrides.lead)
        : createLeadShape();

    return {
        reply: "",
        suggestions: [],
        sessionId: "",
        source: "api-v2",
        leadStatus: "collecting-data",
        leadStage: "collecting",
        scopeStatus: "supported",
        lead,
        missingFields: [],
        readinessScore: 0,
        nextBestAction: "explore",
        closeAttempt: "none",
        canSendQuote: false,
        quoteSendResult: "none",
        quoteSendReason: "",
        retryAfter: 0,
        ...overrides,
        lead
    };
}

function parseJsonLoose(rawValue) {
    const text = String(rawValue || "").trim();

    if (!text) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        const firstBrace = text.indexOf("{");
        const lastBrace = text.lastIndexOf("}");

        if (firstBrace >= 0 && lastBrace > firstBrace) {
            try {
                return JSON.parse(text.slice(firstBrace, lastBrace + 1));
            } catch {
                return null;
            }
        }

        return null;
    }
}

function normalizeResponsePayload(payload, sessionId) {
    const lead = payload?.lead && typeof payload.lead === "object"
        ? createLeadShape(payload.lead)
        : createLeadShape();

    return createResponse({
        reply: String(payload?.reply || "").trim(),
        suggestions: Array.isArray(payload?.suggestions)
            ? payload.suggestions.map((item) => String(item || "").trim()).filter(Boolean).slice(0, 3)
            : [],
        sessionId: String(payload?.sessionId || sessionId),
        source: String(payload?.source || "api-v2"),
        leadStatus: String(payload?.leadStatus || "collecting-data"),
        leadStage: String(payload?.leadStage || "collecting"),
        scopeStatus: String(payload?.scopeStatus || "supported"),
        lead,
        missingFields: Array.isArray(payload?.missingFields) ? payload.missingFields : [],
        readinessScore: Number.isFinite(Number(payload?.readinessScore)) ? Math.max(0, Math.min(100, Number(payload.readinessScore))) : 0,
        nextBestAction: String(payload?.nextBestAction || "explore"),
        closeAttempt: String(payload?.closeAttempt || "none"),
        canSendQuote: Boolean(payload?.canSendQuote),
        quoteSendResult: String(payload?.quoteSendResult || "none"),
        quoteSendReason: String(payload?.quoteSendReason || ""),
        retryAfter: Number.isFinite(Number(payload?.retryAfter)) ? Math.max(0, Math.floor(Number(payload.retryAfter))) : 0
    });
}

function buildNetworkFallback(sessionId) {
    return createResponse({
        reply: "Ahora mismo no pude conectar con el asistente. Intenta de nuevo en unos segundos.",
        sessionId,
        source: "fallback",
        suggestions: [],
        leadStatus: "collecting-data",
        nextBestAction: "explore"
    });
}

export async function requestChatReply(message, options = {}) {
    const endpoint = options.endpoint ?? CHAT_API_ENDPOINT;
    const timeoutMs = options.timeoutMs ?? CHAT_API_TIMEOUT_MS;
    const sessionId = getSessionId();
    const cleanMessage = String(message || "").trim();
    const context = options.context && typeof options.context === "object"
        ? options.context
        : {
            source: "assistant-chat",
            page: "assistant"
        };
    const history = Array.isArray(options.history)
        ? options.history
            .filter((entry) => entry && typeof entry === "object")
            .map((entry) => ({
                role: entry.role === "assistant" ? "assistant" : "user",
                content: String(entry.content || "").trim()
            }))
            .filter((entry) => entry.content.length > 0)
            .slice(-10)
        : [];

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                sessionId,
                message: cleanMessage,
                context,
                history
            }),
            signal: controller.signal
        });

        const rawBody = await response.text().catch(() => "");
        const parsed = parseJsonLoose(rawBody);

        if (!response.ok) {
            const backendMessage = String(parsed?.error?.message || "").trim();

            if (backendMessage) {
                return createResponse({
                    reply: backendMessage,
                    sessionId,
                    source: "policy",
                    retryAfter: Number.isFinite(Number(parsed?.retryAfter)) ? Math.max(0, Math.floor(Number(parsed.retryAfter))) : 0
                });
            }

            return createResponse({
                reply: "No pude procesar tu mensaje en este momento. Intenta nuevamente.",
                sessionId,
                source: "policy"
            });
        }

        if (!parsed || typeof parsed !== "object") {
            return createResponse({
                reply: "La API devolvio una respuesta invalida. Intenta de nuevo en unos segundos.",
                sessionId,
                source: "policy"
            });
        }

        const normalized = normalizeResponsePayload(parsed, sessionId);

        if (!normalized.reply) {
            return createResponse({
                reply: "No pude generar una respuesta util ahora mismo. Intenta nuevamente.",
                sessionId,
                source: "policy"
            });
        }

        return normalized;
    } catch {
        return buildNetworkFallback(sessionId);
    } finally {
        clearTimeout(timer);
    }
}
