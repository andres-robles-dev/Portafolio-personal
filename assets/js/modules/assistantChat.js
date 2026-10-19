import { CHAT_MAX_MESSAGE_CHARS } from "../core/constants.js";
import { requestChatReply } from "../services/chatService.js";
import {
    resolveChatMessageMeta,
    resolveChatPlaceholder,
    resolveChatStatus
} from "./chatFlowSignals.js";

const IDLE_PLACEHOLDER = "Escribe tu mensaje al asistente";
const UI_HISTORY_MAX_ITEMS = 24;

function createMessageNode(role, text, options = {}) {
    const node = document.createElement("article");
    node.className = `assistant-chat-message ${role}`;

    if (options.pending) {
        node.classList.add("pending");
        node.innerHTML = `<span class="assistant-chat-typing" aria-label="Escribiendo"><i></i><i></i><i></i></span>`;
        return node;
    }

    const content = document.createElement("p");
    content.textContent = text;
    node.appendChild(content);

    if (options.meta) {
        const meta = document.createElement("small");
        meta.className = "assistant-chat-message-meta font-mono";
        meta.textContent = options.meta;
        node.appendChild(meta);
    }

    return node;
}

function autoResize(textarea) {
    textarea.style.height = "auto";
    textarea.style.height = `${Math.min(textarea.scrollHeight, 140)}px`;
}

function normalizeSuggestions(input) {
    if (!Array.isArray(input)) {
        return [];
    }

    const clean = [];

    input.forEach((item) => {
        const text = String(item || "").trim();

        if (!text) {
            return;
        }

        if (!clean.includes(text)) {
            clean.push(text);
        }
    });

    return clean.slice(0, 3);
}

export function initAssistantChat({
    shell,
    title,
    messages,
    status,
    form,
    input,
    sendButton
}) {
    if (!shell || !title || !messages || !form || !input || !sendButton) {
        return;
    }

    let isSending = false;
    const history = [];

    const pushHistory = (role, content) => {
        const text = String(content || "").trim();

        if (!text) {
            return;
        }

        history.push({ role, content: text });

        if (history.length > UI_HISTORY_MAX_ITEMS) {
            history.splice(0, history.length - UI_HISTORY_MAX_ITEMS);
        }
    };

    const scrollToEnd = () => {
        requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    };

    const setChatState = (nextState) => {
        const active = nextState === "active";

        shell.dataset.chatState = active ? "active" : "idle";
        shell.classList.toggle("is-active", active);
        title.classList.toggle("is-collapsed", active);
        messages.hidden = !active;

        if (status) {
            status.hidden = !active || status.textContent.trim() === "";
        }
    };

    const addMessage = (role, text, options = {}) => {
        const node = createMessageNode(role, text, options);
        messages.appendChild(node);
        scrollToEnd();
        return node;
    };

    const removeSuggestionRows = () => {
        messages.querySelectorAll(".assistant-chat-suggestions").forEach((row) => {
            row.remove();
        });
    };

    const addSuggestionRow = (items) => {
        const suggestions = normalizeSuggestions(items);

        if (!suggestions.length) {
            return;
        }

        const row = document.createElement("div");
        row.className = "assistant-chat-suggestions";

        suggestions.forEach((text) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "assistant-chat-suggestion font-mono";
            button.textContent = text;
            button.addEventListener("click", async () => {
                if (isSending) {
                    return;
                }

                await sendMessage(text);
                input.focus({ preventScroll: true });
            });
            row.appendChild(button);
        });

        messages.appendChild(row);
        scrollToEnd();
    };

    const setSending = (value) => {
        isSending = value;
        sendButton.disabled = value;
        input.disabled = value;
        sendButton.textContent = value ? "Pensando..." : "Enviar";

        if (value && status && shell.dataset.chatState === "active" && status.textContent.trim() === "") {
            status.textContent = "Procesando...";
            status.dataset.tone = "neutral";
        }
    };

    const applyResponseSignals = (response) => {
        if (status) {
            const nextStatus = resolveChatStatus(response);
            status.textContent = nextStatus.text;
            status.dataset.tone = nextStatus.tone;
            status.hidden = nextStatus.text.trim() === "";
        }

        input.placeholder = resolveChatPlaceholder(response, IDLE_PLACEHOLDER);
    };

    const sendMessage = async (rawText) => {
        const text = String(rawText || "").trim();

        if (!text || isSending) {
            return;
        }

        if (shell.dataset.chatState !== "active") {
            setChatState("active");
        }

        removeSuggestionRows();
        addMessage("user", text);
        pushHistory("user", text);

        setSending(true);
        const pendingNode = addMessage("assistant", "", { pending: true });

        try {
            const response = await requestChatReply(text, {
                context: {
                    source: "assistant-chat",
                    page: "assistant"
                },
                history: history.slice(0, -1)
            });

            const reply = String(response.reply || "").trim() || "No pude generar una respuesta ahora mismo.";
            const node = createMessageNode("assistant", reply, {
                meta: resolveChatMessageMeta(response)
            });

            pendingNode.replaceWith(node);
            pushHistory("assistant", reply);
            applyResponseSignals(response);
            addSuggestionRow(response.suggestions);
            scrollToEnd();
        } finally {
            setSending(false);
        }
    };

    setChatState("idle");
    messages.innerHTML = "";
    input.placeholder = IDLE_PLACEHOLDER;

    if (status) {
        status.textContent = "";
        status.dataset.tone = "neutral";
        status.hidden = true;
    }

    input.maxLength = CHAT_MAX_MESSAGE_CHARS;
    input.addEventListener("input", () => autoResize(input));
    autoResize(input);

    input.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const value = input.value;

        if (!value.trim()) {
            input.focus();
            return;
        }

        input.value = "";
        autoResize(input);
        await sendMessage(value);
        input.focus();
    });

    input.focus({ preventScroll: true });
}
