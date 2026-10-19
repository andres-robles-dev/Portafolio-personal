function setActiveNav(links, target) {
    links.forEach((link) => {
        const isActive = link.getAttribute("data-nav") === target;
        link.classList.toggle("is-active", isActive);

        if (isActive) {
            link.setAttribute("aria-current", "page");
        } else {
            link.removeAttribute("aria-current");
        }
    });
}

function setHeaderTone(mainNav, target) {
    const darkTargets = new Set(["method", "contact"]);
    mainNav?.classList.toggle("is-dark", darkTargets.has(target));
}

export function initNavigation({ elements, state, updateOverflow, showSections, goTo }) {
    const desktopLinks = Array.from(elements.navLinks);
    const panelLinks = Array.from(elements.navPanelLinks);
    const allNavLinks = [...desktopLinks, ...panelLinks];

    function toggleMenu(force) {
        if (!elements.navPanel || !elements.menuToggle) {
            return;
        }

        const shouldOpen = typeof force === "boolean" ? force : !elements.navPanel.classList.contains("active");

        elements.navPanel.classList.toggle("active", shouldOpen);
        elements.menuToggle.classList.toggle("active", shouldOpen);
        elements.navPanel.setAttribute("aria-hidden", shouldOpen ? "false" : "true");
        elements.menuToggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
        elements.menuToggle.setAttribute("aria-label", shouldOpen ? "Cerrar menu" : "Abrir menu");

        state.menuOpen = shouldOpen;
        updateOverflow(state);
    }

    function closeMenuOnOutsideClick(event) {
        if (!state.menuOpen || !elements.navPanel || !elements.menuToggle) {
            return;
        }

        const target = event.target;

        if (!(target instanceof Node)) {
            return;
        }

        if (elements.navPanel.contains(target) || elements.menuToggle.contains(target)) {
            return;
        }

        toggleMenu(false);
    }

    function closeMenuOnEscape(event) {
        if (event.key === "Escape") {
            toggleMenu(false);
        }
    }

    function closeMenuOnDesktop() {
        if (window.innerWidth > 1023) {
            toggleMenu(false);
        }
    }

    function setNavAndGo(target) {
        setActiveNav(allNavLinks, target);
        setHeaderTone(elements.mainNav, target);
        goTo(target);
    }

    function initSectionObserver() {
        const trackable = [
            { key: "about", element: elements.aboutSection },
            { key: "method", element: elements.experienceSection },
            { key: "work", element: elements.projectsSection },
            { key: "contact", element: elements.contactSection }
        ].filter((item) => item.element);

        const ratios = new Map(trackable.map((item) => [item.key, 0]));

        const observer = new IntersectionObserver((entries) => {
            if (!state.sectionsVisible) {
                setActiveNav(allNavLinks, "home");
                setHeaderTone(elements.mainNav, "home");
                return;
            }

            entries.forEach((entry) => {
                const current = trackable.find((item) => item.element === entry.target);

                if (!current) {
                    return;
                }

                ratios.set(current.key, entry.isIntersecting ? entry.intersectionRatio : 0);
            });

            let bestKey = "about";
            let bestRatio = 0;

            ratios.forEach((ratio, key) => {
                if (ratio > bestRatio) {
                    bestRatio = ratio;
                    bestKey = key;
                }
            });

            if (bestRatio > 0) {
                setActiveNav(allNavLinks, bestKey);
                setHeaderTone(elements.mainNav, bestKey);
            }
        }, {
            threshold: [0.2, 0.4, 0.65],
            rootMargin: "-18% 0px -45% 0px"
        });

        trackable.forEach((item) => observer.observe(item.element));
    }

    desktopLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            setNavAndGo(link.getAttribute("data-nav"));
        });
    });

    elements.menuToggle?.addEventListener("click", () => toggleMenu());
    elements.menuToggle?.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            toggleMenu();
        }
    });

    if (elements.menuToggle && elements.navPanel) {
        elements.menuToggle.setAttribute("aria-controls", "navPanel");
        elements.menuToggle.setAttribute("aria-expanded", "false");
        elements.menuToggle.setAttribute("aria-label", "Abrir menu");
        elements.navPanel.setAttribute("aria-hidden", "true");
    }

    panelLinks.forEach((anchor) => {
        anchor.addEventListener("click", (event) => {
            event.preventDefault();
            toggleMenu(false);
            setNavAndGo(anchor.getAttribute("data-nav"));
        });
    });

    elements.contactButton?.addEventListener("click", () => {
        if (!state.sectionsVisible) {
            setActiveNav(allNavLinks, "contact");
            setHeaderTone(elements.mainNav, "contact");
            showSections("contact");
            return;
        }

        setNavAndGo("contact");
    });

    initSectionObserver();
    setActiveNav(allNavLinks, "home");
    setHeaderTone(elements.mainNav, "home");

    document.addEventListener("click", closeMenuOnOutsideClick);
    document.addEventListener("keydown", closeMenuOnEscape);
    window.addEventListener("resize", closeMenuOnDesktop);

    return {
        toggleMenu
    };
}
