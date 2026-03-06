function setActiveLinks(activeKey) {
    const links = document.querySelectorAll("[data-nav-key]");

    links.forEach((link) => {
        const isActive = link.getAttribute("data-nav-key") === activeKey;
        link.classList.toggle("is-active", isActive);

        if (isActive) {
            link.setAttribute("aria-current", "page");
        } else {
            link.removeAttribute("aria-current");
        }
    });
}

function initToneObserver(mainNav) {
    if (!mainNav) {
        return;
    }

    const darkZones = document.querySelectorAll("[data-nav-tone='dark']");

    if (!darkZones.length) {
        return;
    }

    const visibility = new Map();

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            visibility.set(entry.target, entry.isIntersecting && entry.intersectionRatio > 0.02);
        });

        const shouldDark = Array.from(visibility.values()).some(Boolean);
        mainNav.classList.toggle("is-dark", shouldDark);
    }, {
        threshold: [0, 0.02, 0.08],
        rootMargin: "-8% 0px -84% 0px"
    });

    darkZones.forEach((zone) => {
        visibility.set(zone, false);
        observer.observe(zone);
    });
}

export function initStaticNav() {
    const body = document.body;
    const menuToggle = document.querySelector(".menu-toggle");
    const navPanel = document.getElementById("navPanel");
    const mainNav = document.querySelector(".main-nav");
    const activeKey = body.getAttribute("data-nav-active") || "home";

    setActiveLinks(activeKey);
    initToneObserver(mainNav);

    if (!menuToggle || !navPanel) {
        return;
    }

    function toggleMenu(force) {
        const shouldOpen = typeof force === "boolean" ? force : !navPanel.classList.contains("active");

        navPanel.classList.toggle("active", shouldOpen);
        menuToggle.classList.toggle("active", shouldOpen);
        navPanel.setAttribute("aria-hidden", shouldOpen ? "false" : "true");
        menuToggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
        menuToggle.setAttribute("aria-label", shouldOpen ? "Cerrar menu" : "Abrir menu");
        document.documentElement.style.overflowY = shouldOpen ? "hidden" : "auto";
        document.body.style.overflowY = shouldOpen ? "hidden" : "auto";
    }

    function handleDocumentClick(event) {
        if (!navPanel.classList.contains("active")) {
            return;
        }

        const target = event.target;

        if (!(target instanceof Node)) {
            return;
        }

        if (navPanel.contains(target) || menuToggle.contains(target)) {
            return;
        }

        toggleMenu(false);
    }

    function handleEscape(event) {
        if (event.key === "Escape") {
            toggleMenu(false);
        }
    }

    function handleResize() {
        if (window.innerWidth > 1023) {
            toggleMenu(false);
        }
    }

    menuToggle.setAttribute("aria-controls", "navPanel");
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Abrir menu");
    navPanel.setAttribute("aria-hidden", "true");

    menuToggle.addEventListener("click", () => toggleMenu());
    menuToggle.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            toggleMenu();
        }
    });

    navPanel.querySelectorAll("a").forEach((anchor) => {
        anchor.addEventListener("click", () => {
            toggleMenu(false);
        });
    });

    document.addEventListener("click", handleDocumentClick);
    document.addEventListener("keydown", handleEscape);
    window.addEventListener("resize", handleResize);
}

initStaticNav();
