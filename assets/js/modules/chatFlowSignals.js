const DEFAULT_CHAT_PLACEHOLDER = "Escribe tu mensaje...";

const CHAT_FIELD_LABELS = Object.freeze({
    projectType: "tipo de proyecto",
    problem: "problema actual",
    objective: "objetivo principal",
    timeline: "plazo estimado",
    "contact.name": "nombre",
    "contact.email": "correo"
});

const CHAT_FIELD_PLACEHOLDERS = Object.freeze({
    projectType: "Ejemplo: web corporativa, landing o API",
    problem: "Que problema quieres resolver ahora?",
    objective: "Cual es el objetivo principal del proyecto?",
    timeline: "Que plazo tienes en mente para la fase 1?",
    "contact.name": "Si quieres cotizacion real, comparte tu nombre",
    "contact.email": "Comparte tu correo (ej: nombre@empresa.com)"
});

const CHAT_FIELD_PRIORITY = Object.freeze([
    "projectType",
    "problem",
    "objective",
    "timeline",
    "contact.name",
    "contact.email"
]);

export function normalizeChatFields(input) {
    if (!Array.isArray(input)) {
        return [];
    }

    return input
        .map((value) => String(value || "").trim())
        .filter((value) => value.length > 0);
}

function asNaturalList(items) {
    if (!items.length) {
        return "";
    }

    if (items.length === 1) {
        return items[0];
    }

    if (items.length === 2) {
        return `${items[0]} y ${items[1]}`;
    }

    return `${items.slice(0, -1).join(", ")} y ${items[items.length - 1]}`;
}

function toChatFieldLabels(fields) {
    return fields
        .map((field) => CHAT_FIELD_LABELS[field] || field)
        .filter((label) => label.length > 0);
}

function pickPriorityField(fields) {
    for (const field of CHAT_FIELD_PRIORITY) {
        if (fields.includes(field)) {
            return field;
        }
    }

    return "";
}

function formatRetryAfter(seconds) {
    const safe = Math.max(0, Number(seconds) || 0);

    if (safe < 60) {
        return `${safe}s`;
    }

    const minutes = Math.ceil(safe / 60);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.ceil(minutes / 60);
    return `${hours} h`;
}

export function resolveChatMessageMeta(response) {
    if (response.source === 'demo') return 'Demostracion sin IA externa';
    if (response.source === "fallback") {
        return "Modo degradado";
    }

    return "";
}

export function resolveChatStatus(response) {
    if (response.source === 'demo') return { tone: 'neutral', text: 'Modo demo: sin envios reales ni memoria de contactos.' };
    const missingFields = normalizeChatFields(response.missingFields);
    const nextBestAction = String(response.nextBestAction || "").trim();
    const leadStatus = String(response.leadStatus || "").trim();
    const scopeStatus = String(response.scopeStatus || "supported").trim();
    const readinessScore = Math.max(0, Number(response.readinessScore) || 0);
    const retryAfter = Math.max(0, Number(response.retryAfter) || 0);
    const quoteSendResult = String(response.quoteSendResult || "none").trim();

    if (retryAfter > 0) {
        return {
            tone: "warning",
            text: `Pausa temporal activa. Reintenta en ${formatRetryAfter(retryAfter)}.`
        };
    }

    if (quoteSendResult === "queued") {
        return {
            tone: "warning",
            text: "Correo no disponible: solicitud guardada para seguimiento."
        };
    }

    if (quoteSendResult === "failed") {
        return {
            tone: "warning",
            text: "No se pudo enviar por correo en este momento."
        };
    }

    if (quoteSendResult === "blocked_missing_contact") {
        return {
            tone: "neutral",
            text: "Falta tu nombre y correo para poder enviarla."
        };
    }

    if (quoteSendResult === "blocked_missing_brief") {
        return {
            tone: "neutral",
            text: "Aun faltan datos clave del briefing antes de enviarla."
        };
    }

    if (quoteSendResult === "blocked_scope_reframe") {
        return {
            tone: "neutral",
            text: "Primero debemos ajustar el alcance a una alternativa web viable."
        };
    }

    if (leadStatus === "sent-to-andres") {
        return {
            tone: "success",
            text: "Solicitud enviada a Andres."
        };
    }

    if (nextBestAction === "close_final" && Boolean(response.canSendQuote)) {
        return {
            tone: "neutral",
            text: "Todo listo para confirmar envio de cotizacion. Escribe: confirmo enviar cotizacion."
        };
    }

    if (nextBestAction === "close_soft") {
        return {
            tone: "neutral",
            text: "Ya casi esta: faltan datos de contacto para cerrar la cotizacion."
        };
    }

    if (nextBestAction === "reframe") {
        return {
            tone: "neutral",
            text: "Ajustemos enfoque: podemos resolverlo mejor con alternativa web por fases."
        };
    }

    if (scopeStatus === "needs-reframe") {
        return {
            tone: "neutral",
            text: "El objetivo detectado encaja mejor con una solucion web por fases."
        };
    }

    if (missingFields.length) {
        const labels = toChatFieldLabels(missingFields);

        return {
            tone: "neutral",
            text: labels.length ? `Para afinar la propuesta faltan: ${asNaturalList(labels)}.` : "Sigamos afinando detalles del proyecto."
        };
    }

    if (response.source === "fallback") {
        return {
            tone: "warning",
            text: "Conexion temporalmente inestable con la API."
        };
    }

    if (readinessScore > 0) {
        return {
            tone: "neutral",
            text: `Contexto comercial estimado: ${Math.round(readinessScore)}%.`
        };
    }

    return {
        tone: "neutral",
        text: ""
    };
}

export function resolveChatPlaceholder(response, fallbackPlaceholder = DEFAULT_CHAT_PLACEHOLDER) {
    const missingFields = normalizeChatFields(response.missingFields);
    const missingField = pickPriorityField(missingFields);
    return CHAT_FIELD_PLACEHOLDERS[missingField] || fallbackPlaceholder;
}

export { DEFAULT_CHAT_PLACEHOLDER };
