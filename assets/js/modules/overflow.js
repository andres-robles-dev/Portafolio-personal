export function updateOverflow(state) {
    const shouldLock = !state.sectionsVisible || state.heroTransitioning || state.menuOpen || state.modalOpen;
    document.documentElement.style.overflowY = shouldLock ? "hidden" : "auto";
    document.body.style.overflowY = shouldLock ? "hidden" : "auto";
}
