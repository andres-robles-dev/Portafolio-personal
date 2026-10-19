const heroSection = document.getElementById("heroSection");
const aboutSection = document.getElementById("aboutSection");
const experienceSection = document.getElementById("experienceSection");
const contactSection = document.getElementById("contactSection");
const footerSection = document.getElementById("footerSection");
const centerColumn = document.getElementById("centerColumn");
const mainNav = document.querySelector(".main-nav");
const navLinks = document.querySelectorAll(".nav-link[data-nav]");
const menuToggle = document.querySelector(".menu-toggle");
const navPanel = document.getElementById("navPanel");
const navPanelLinks = navPanel?.querySelectorAll("a[data-nav]") ?? [];
const contactButton = document.querySelector(".contact-btn");
const revealElements = document.querySelectorAll(".reveal");
const contactForm = document.getElementById("contactForm");
const contactFeedback = document.getElementById("contactFeedback");
const contactSubmitButton = document.getElementById("contactSubmitButton");
const contactIntentButtons = document.querySelectorAll("[data-contact-intent]");

export const elements = {
    heroSection,
    aboutSection,
    experienceSection,
    contactSection,
    footerSection,
    centerColumn,
    mainNav,
    navLinks,
    menuToggle,
    navPanel,
    navPanelLinks,
    contactButton,
    revealElements,
    contactForm,
    contactFeedback,
    contactSubmitButton,
    contactIntentButtons
};
