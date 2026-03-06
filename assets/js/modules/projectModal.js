import { getProductBySlug } from "../data/products.js";

const MODAL_FALLBACK_IMAGE = "img/f6bb6f77c2fecaa5fe5e945b4db402d2.jpg";

function getSlugFromAnchor(anchor) {
    const direct = anchor.getAttribute("data-project-slug");

    if (direct) {
        return direct;
    }

    try {
        const href = anchor.getAttribute("href") || "";
        const url = new URL(href, window.location.href);
        return url.searchParams.get("slug") || "";
    } catch {
        return "";
    }
}

function renderList(container, items, className) {
    container.innerHTML = "";

    items.forEach((item) => {
        const li = document.createElement("li");
        li.className = className;
        li.textContent = item;
        container.appendChild(li);
    });
}

function renderTagList(container, tags) {
    container.innerHTML = "";

    tags.forEach((tag) => {
        const span = document.createElement("span");
        span.className = "project-modal-tag font-mono";
        span.textContent = tag;
        container.appendChild(span);
    });
}

function renderGallery(container, gallery, fallbackAlt) {
    container.innerHTML = "";

    const cover = Array.isArray(gallery) && gallery.length > 0
        ? gallery[0]
        : { src: MODAL_FALLBACK_IMAGE, alt: fallbackAlt };

    const figure = document.createElement("figure");
    figure.className = "project-modal-media is-featured";

    const img = document.createElement("img");
    img.src = cover.src || MODAL_FALLBACK_IMAGE;
    img.alt = cover.alt || fallbackAlt;
    img.loading = "eager";
    img.decoding = "async";
    img.referrerPolicy = "no-referrer";
    img.addEventListener("error", () => {
        if (img.src.includes(MODAL_FALLBACK_IMAGE)) {
            return;
        }

        img.src = MODAL_FALLBACK_IMAGE;
    }, { once: true });

    figure.appendChild(img);
    container.appendChild(figure);
}

function renderStoryGallery(container, gallery, fallbackAlt) {
    if (!container) {
        return;
    }

    container.innerHTML = "";

    const items = Array.isArray(gallery) && gallery.length > 1
        ? gallery.slice(1, 4)
        : [];

    if (items.length === 0) {
        container.hidden = true;
        return;
    }

    container.hidden = false;

    items.forEach((item) => {
        const figure = document.createElement("figure");
        figure.className = "project-modal-story-media";

        const img = document.createElement("img");
        img.src = item.src || MODAL_FALLBACK_IMAGE;
        img.alt = item.alt || fallbackAlt;
        img.loading = "lazy";
        img.decoding = "async";
        img.referrerPolicy = "no-referrer";
        img.addEventListener("error", () => {
            if (img.src.includes(MODAL_FALLBACK_IMAGE)) {
                return;
            }

            img.src = MODAL_FALLBACK_IMAGE;
        }, { once: true });

        figure.appendChild(img);
        container.appendChild(figure);
    });
}

export function initProjectModal({ state, updateOverflow }) {
    const modal = document.getElementById("projectModal");

    if (!modal) {
        return;
    }

    const closeButton = document.getElementById("projectModalClose");
    const title = document.getElementById("projectModalTitle");
    const kicker = document.getElementById("projectModalKicker");
    const subtitle = document.getElementById("projectModalSubtitle");
    const tags = document.getElementById("projectModalTags");
    const gallery = document.getElementById("projectModalGallery");
    const storyGallery = document.getElementById("projectModalStoryGallery");
    const summary = document.getElementById("projectModalSummary");
    const deliverables = document.getElementById("projectModalDeliverables");
    const timeline = document.getElementById("projectModalTimeline");
    const status = document.getElementById("projectModalStatus");
    const demoLink = document.getElementById("projectModalDemoLink");
    const demoHint = document.getElementById("projectModalDemoHint");
    const closeTriggers = modal.querySelectorAll("[data-project-modal-close]");
    const projectAnchors = document.querySelectorAll(".project-item-link[data-project-slug]");

    let lastTrigger = null;

    function setDemoState(product) {
        const hasDemo = Boolean(product.realDemoUrl);

        demoLink.href = hasDemo ? product.realDemoUrl : "#";
        demoLink.textContent = hasDemo ? "Ver demo real" : "Ver demo real (proximamente)";
        demoLink.classList.toggle("is-disabled", !hasDemo);

        if (hasDemo) {
            demoLink.removeAttribute("aria-disabled");
            demoHint.textContent = "Demo funcional publicada.";
            return;
        }

        demoLink.setAttribute("aria-disabled", "true");
        demoHint.textContent = "Estamos construyendo esta demo real. Te la compartimos en la siguiente fase.";
    }

    function fillModal(product) {
        const firstParagraph = product.summary?.[0] || "";

        title.textContent = product.name;
        kicker.textContent = product.kicker;
        subtitle.textContent = product.subtitle;
        summary.textContent = firstParagraph;
        timeline.textContent = product.timeline;
        status.textContent = product.status;

        renderTagList(tags, product.tags || []);
        renderGallery(gallery, product.gallery || [], product.name);
        renderStoryGallery(storyGallery, product.gallery || [], product.name);
        renderList(deliverables, product.deliverables || [], "project-modal-deliverable");
        setDemoState(product);
    }

    function openModal(trigger, product) {
        fillModal(product);
        lastTrigger = trigger;
        state.modalOpen = true;
        updateOverflow(state);

        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("project-modal-open");

        closeButton?.focus({ preventScroll: true });
    }

    function closeModal() {
        if (!modal.classList.contains("is-open")) {
            return;
        }

        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("project-modal-open");

        state.modalOpen = false;
        updateOverflow(state);

        if (lastTrigger instanceof HTMLElement) {
            lastTrigger.focus({ preventScroll: true });
        }
    }

    projectAnchors.forEach((anchor) => {
        anchor.addEventListener("click", (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const slug = getSlugFromAnchor(anchor);
            const product = getProductBySlug(slug);

            if (!product) {
                return;
            }

            event.preventDefault();
            openModal(anchor, product);
        });
    });

    closeTriggers.forEach((trigger) => {
        trigger.addEventListener("click", () => closeModal());
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeModal();
        }
    });

    demoLink?.addEventListener("click", (event) => {
        if (demoLink.classList.contains("is-disabled")) {
            event.preventDefault();
        }
    });
}
