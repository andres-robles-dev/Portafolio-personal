import {
    HERO_HIDE_DURATION_MS,
    HERO_HIDE_PHOTO_DURATION_MS,
    HERO_RETURN_DELAY_MS,
    SECTION_SCROLL_DELAYS,
    SECTION_STAGGER_DELAYS
} from "../core/constants.js";

export function createTransitions({ elements, state, updateOverflow }) {
    const targetMap = {
        about: elements.aboutSection,
        method: elements.experienceSection,
        work: elements.projectsSection,
        contact: elements.contactSection
    };

    let hideTimerId;
    let scrollTimerId;

    function clearTransitionTimers() {
        if (hideTimerId) {
            clearTimeout(hideTimerId);
            hideTimerId = undefined;
        }

        if (scrollTimerId) {
            clearTimeout(scrollTimerId);
            scrollTimerId = undefined;
        }
    }

    function activateSections(target) {
        elements.heroSection.classList.add("section-hidden");

        elements.aboutSection.classList.add("active");
        setTimeout(() => elements.experienceSection.classList.add("active"), SECTION_STAGGER_DELAYS.experience);
        setTimeout(() => elements.projectsSection.classList.add("active"), SECTION_STAGGER_DELAYS.projects);
        setTimeout(() => {
            elements.contactSection.classList.add("active");
            elements.footerSection.classList.add("active");
        }, SECTION_STAGGER_DELAYS.contactAndFooter);

        const scrollDelay = SECTION_SCROLL_DELAYS[target] ?? SECTION_SCROLL_DELAYS.about;

        scrollTimerId = setTimeout(() => {
            (targetMap[target] || elements.aboutSection).scrollIntoView({ behavior: "smooth", block: "start" });
        }, scrollDelay);
    }

    function showSections(target = "about", options = {}) {
        if (state.sectionsVisible || state.heroTransitioning) {
            return;
        }

        const isPhotoTransition = options?.source === "photo";
        const hideDuration = isPhotoTransition ? HERO_HIDE_PHOTO_DURATION_MS : HERO_HIDE_DURATION_MS;

        clearTransitionTimers();

        state.heroTransitioning = true;
        updateOverflow(state);

        elements.heroSection.classList.toggle("is-photo-transition", isPhotoTransition);

        requestAnimationFrame(() => {
            elements.heroSection.classList.add("hiding");
        });

        hideTimerId = setTimeout(() => {
            activateSections(target);

            state.sectionsVisible = true;
            state.heroTransitioning = false;
            updateOverflow(state);
        }, hideDuration);
    }

    function goHome() {
        if (!state.sectionsVisible && !state.heroTransitioning) {
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }

        clearTransitionTimers();

        state.sectionsVisible = false;
        state.heroTransitioning = true;
        updateOverflow(state);

        elements.aboutSection.classList.remove("active");
        elements.experienceSection.classList.remove("active");
        elements.projectsSection.classList.remove("active");
        elements.contactSection.classList.remove("active");
        elements.footerSection.classList.remove("active");

        elements.heroSection.classList.remove("section-hidden");

        setTimeout(() => {
            elements.heroSection.classList.remove("hiding");
            elements.heroSection.classList.remove("is-photo-transition");

            state.heroTransitioning = false;
            updateOverflow(state);

            window.scrollTo({ top: 0, behavior: "smooth" });
        }, HERO_RETURN_DELAY_MS);
    }

    function goTo(target) {
        if (target === "home") {
            goHome();
            return;
        }

        if (state.heroTransitioning) {
            return;
        }

        if (!state.sectionsVisible) {
            showSections(target);
            return;
        }

        const section = targetMap[target];

        if (!section) {
            return;
        }

        section.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    return {
        showSections,
        goHome,
        goTo
    };
}
