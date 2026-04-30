// Modal open/close helpers for ledger adjustment dialogs.
// Pure DOM — no framework, no jQuery.

export function initAdjustmentModals() {
    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal')));
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
    });

    document.querySelectorAll('[data-ledger-adjustment-cancel]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('[data-ledger-adjustment-modal]');
            if (modal && modal.id) {
                closeModal(modal.id);
            }
        });
    });

    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllAdjustmentModals();
        }
    });
}

export function openModal(id) {
    const el = id ? document.getElementById(id) : null;
    if (!el) {
        return;
    }
    el.classList.remove('hidden');
    el.classList.add('flex');
}

export function closeModal(id) {
    const el = id ? document.getElementById(id) : null;
    if (!el) {
        return;
    }
    el.classList.add('hidden');
    el.classList.remove('flex');
    resetCreateAdjustmentForm(el);
}

export function closeAllAdjustmentModals() {
    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resetCreateAdjustmentForm(modal);
    });
}

// Reset create-adjustment forms (credit note / refund) when their modal closes
// so reopening shows a clean form with today's date. The edit modal is excluded —
// its values are populated from the API on open.
function resetCreateAdjustmentForm(modal) {
    const form = modal.querySelector('form[data-ledger-adjustment-form]');
    if (!form) {
        return;
    }
    form.reset();
    const dateInput = form.querySelector('input[name="recorded_at"]');
    if (dateInput) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }
    clearFieldErrors(form);
}

export function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

export function renderValidationErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const target = form.querySelector(`[data-error-for="${field}"]`);
        if (target) {
            target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            target.classList.remove('hidden');
        }
    });
}
