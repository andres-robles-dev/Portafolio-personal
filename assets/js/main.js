import { elements } from "./core/dom.js";
import { state } from "./core/state.js";
import { updateOverflow } from "./modules/overflow.js";
import { createTransitions } from "./modules/transitions.js";
import { initNavigation } from "./modules/navigation.js";
import { initReveal } from "./modules/reveal.js";
import { initContact } from "./modules/contact.js";
import { initProjectModal } from "./modules/projectModal.js";
import { initDuckRunner } from "./modules/duckRunner.js";

updateOverflow(state);

const { showSections, goTo } = createTransitions({
    elements,
    state,
    updateOverflow
});

initNavigation({
    elements,
    state,
    updateOverflow,
    showSections,
    goTo
});

initContact({
    contactForm: elements.contactForm,
    contactFeedback: elements.contactFeedback,
    contactSubmitButton: elements.contactSubmitButton,
    contactIntentButtons: elements.contactIntentButtons
});

initProjectModal({
    state,
    updateOverflow
});

initDuckRunner();

initReveal(elements.revealElements);

const entryTarget = new URLSearchParams(window.location.search).get("go");
const validTargets = new Set(["home", "about", "method", "work", "contact"]);

if (entryTarget && validTargets.has(entryTarget)) {
    requestAnimationFrame(() => {
        goTo(entryTarget);
    });
}
