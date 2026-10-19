import { CONTACT_API_ENDPOINT, CONTACT_API_TIMEOUT_MS } from "../core/constants.js";
import { getQueuedContactsCount, submitContact, clearQueuedContacts } from "../services/contactService.js";

function normalizePayload(form) {
    const formData = new FormData(form);

    return {
        name: String(formData.get("name") || "").trim(),
        email: String(formData.get("email") || "").trim(),
        company: String(formData.get("company") || "").trim(),
        inquiryType: String(formData.get("inquiryType") || "").trim(),
        message: String(formData.get("message") || "").trim(),
        website: String(formData.get("website") || "").trim(),
        source: String(formData.get("source") || "portfolio-web"),
        formStartedAt: String(formData.get("formStartedAt") || "").trim(),
        submittedAt: Date.now(),
        createdAt: new Date().toISOString()
    };
}

function markFormStart(form) {
    const startedAtField = form?.elements?.formStartedAt;

    if (!startedAtField) {
        return;
    }

    startedAtField.value = String(Date.now());
}

function validatePayload(payload) {
    const errors = {};
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (payload.name.length < 2) {
        errors.name = "Escribe tu nombre completo.";
    }

    if (!emailRegex.test(payload.email)) {
        errors.email = "Introduce un email válido.";
    }

    if (!payload.inquiryType) {
        errors.inquiryType = "Selecciona el tipo de solicitud.";
    }

    if (payload.message.length < 20) {
        errors.message = "Cuéntame un poco más (mínimo 20 caracteres).";
    }

    return errors;
}

function updateFieldErrors(form, errors) {
    const fields = ["name", "email", "company", "inquiryType", "message"];

    fields.forEach((fieldName) => {
        const field = form.elements[fieldName];
        const errorNode = form.querySelector(`[data-error-for="${fieldName}"]`);
        const message = errors[fieldName] || "";

        if (field) {
            field.classList.toggle("is-invalid", Boolean(message));

            if (message) {
                field.setAttribute("aria-invalid", "true");
            } else {
                field.removeAttribute("aria-invalid");
            }
        }

        if (errorNode) {
            errorNode.textContent = message;
        }
    });
}

function setFeedback(feedbackNode, type, message) {
    if (!feedbackNode) {
        return;
    }

    feedbackNode.textContent = message;
    feedbackNode.classList.remove("is-error", "is-success", "is-warning");

    if (type) {
        feedbackNode.classList.add(`is-${type}`);
    }
}

export function initContact({
    contactForm,
    contactFeedback,
    contactSubmitButton,
    contactIntentButtons
}) {
    if (!contactForm) {
        return;
    }

    markFormStart(contactForm);

    const pendingItems = getQueuedContactsCount();

    if (pendingItems > 0) {
        setFeedback(
            contactFeedback,
            "warning",
            `Hay ${pendingItems} borrador/es de una version anterior. No se reenviaran automaticamente.`
        );

        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.textContent = 'Borrar borradores antiguos';
        clearButton.addEventListener('click', () => {
            setFeedback(contactFeedback, 'warning', clearQueuedContacts()
                ? 'Borradores antiguos eliminados de este navegador.' : 'No se pudo acceder al almacenamiento del navegador.');
        });
        contactFeedback?.appendChild(clearButton);
    }

    function setLoading(loading) {
        if (!contactSubmitButton) {
            return;
        }

        contactSubmitButton.disabled = loading;
        contactSubmitButton.textContent = loading ? "Enviando..." : "Enviar solicitud";
    }

    contactIntentButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const intent = button.getAttribute("data-contact-intent");
            const typeField = contactForm.elements.inquiryType;

            if (!typeField) {
                return;
            }

            typeField.value = intent;

            if (intent === "producto") {
                setFeedback(contactFeedback, "", "Genial. Te ayudo a aterrizar una solucion que encaje con tu objetivo.");
            } else if (intent === "medida") {
                setFeedback(contactFeedback, "", "Perfecto. Revisamos juntos un proyecto a medida.");
            } else if (intent === "equipo") {
                setFeedback(contactFeedback, "", "Buenisimo. Cuentame el contexto y vemos como me sumo a tu equipo.");
            }

            contactForm.elements.message?.focus();
        });
    });

    contactForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        const payload = normalizePayload(contactForm);
        const errors = validatePayload(payload);

        updateFieldErrors(contactForm, errors);

        if (Object.keys(errors).length) {
            setFeedback(contactFeedback, "error", "Revisa los campos marcados para poder enviar.");
            return;
        }

        setLoading(true);
        setFeedback(contactFeedback, "", "");

        const result = await submitContact(payload, {
            endpoint: CONTACT_API_ENDPOINT,
            timeoutMs: CONTACT_API_TIMEOUT_MS
        });

        setLoading(false);

        if (result.status === "sent") {
            contactForm.reset();
            markFormStart(contactForm);
            updateFieldErrors(contactForm, {});
            setFeedback(contactFeedback, "success", "Solicitud recibida por el servidor para seguimiento.");
            return;
        }

        if (result.status === 'demo') {
            setFeedback(contactFeedback, 'warning', 'Demostracion completada: no se han guardado tus datos ni enviado correos.');
            return;
        }

        markFormStart(contactForm);

        updateFieldErrors(contactForm, result.fields || {});
        setFeedback(
            contactFeedback,
            "warning",
            result.message || 'No se pudo enviar la solicitud. No se ha guardado un borrador en el navegador.'
        );
    });
}
