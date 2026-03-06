export const HERO_HIDE_DURATION_MS = 1050;
export const HERO_HIDE_PHOTO_DURATION_MS = 960;
export const HERO_RETURN_DELAY_MS = 40;

export const SECTION_STAGGER_DELAYS = Object.freeze({
    experience: 220,
    projects: 440,
    contactAndFooter: 660
});

export const SECTION_SCROLL_DELAYS = Object.freeze({
    about: 120,
    method: 360,
    work: 520,
    contact: 740
});

export const CONTACT_API_ENDPOINT = "/api/contact.php";
export const CONTACT_API_TIMEOUT_MS = 8000;
export const CONTACT_QUEUE_STORAGE_KEY = "portfolio_contact_queue_v1";

export const CHAT_API_ENDPOINT = "/api/chat.php";
export const CHAT_API_TIMEOUT_MS = 10000;
export const CHAT_SESSION_STORAGE_KEY = "portfolio_chat_session_v1";
export const CHAT_MAX_MESSAGE_CHARS = 500;
