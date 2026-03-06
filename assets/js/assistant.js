import { initAssistantChat } from "./modules/assistantChat.js";

initAssistantChat({
    shell: document.getElementById("assistantChatShell"),
    title: document.getElementById("assistantChatTitle"),
    messages: document.getElementById("assistantChatMessages"),
    status: document.getElementById("assistantChatStatus"),
    form: document.getElementById("assistantChatForm"),
    input: document.getElementById("assistantChatInput"),
    sendButton: document.getElementById("assistantChatSend")
});
