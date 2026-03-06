export function initReveal(revealElements) {
    revealElements.forEach((element, index) => {
        const delay = (index % 6) * 55;
        element.style.setProperty("--reveal-delay", `${delay}ms`);
    });

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add("active");
            obs.unobserve(entry.target);
        });
    }, {
        threshold: 0.14,
        rootMargin: "0px 0px -60px 0px"
    });

    revealElements.forEach((element) => observer.observe(element));
}
