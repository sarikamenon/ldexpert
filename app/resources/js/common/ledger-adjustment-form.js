import { successToast, errorAlert } from './sweetalert';

/**
 * Initialize all ledger adjustment forms on the page.
 * Each form is identified by the [data-ledger-adjustment-form] attribute.
 *
 * @param {Object} options
 * @param {Function} [options.onSuccess] - Called with (responseJson, formEl) on a successful submit.
 *                                          Defaults to a success toast + page reload.
 */
export function initLedgerAdjustmentForms(options = {}) {
    const forms = document.querySelectorAll('[data-ledger-adjustment-form]');
    forms.forEach((form) => attachSubmitHandler(form, options));
}

function attachSubmitHandler(form, options) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalLabel = submitBtn ? submitBtn.innerHTML : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving…';
        }

        try {
            const formData = new FormData(form);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || formData.get('_token');

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                renderValidationErrors(form, data.errors || {});
                return;
            }

            if (!response.ok || data.success === false) {
                await errorAlert(data.message || 'An error occurred while processing your request.');
                return;
            }

            if (typeof options.onSuccess === 'function') {
                await options.onSuccess(data, form);
                return;
            }

            await successToast(data.message || 'Saved successfully.');
            window.location.reload();
        } catch (err) {
            console.error('Ledger adjustment submit failed', err);
            await errorAlert('An unexpected error occurred. Please try again.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                if (originalLabel !== null) {
                    submitBtn.innerHTML = originalLabel;
                }
            }
        }
    });
}

function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
}

function renderValidationErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const target = form.querySelector(`[data-error-for="${field}"]`);
        if (target) {
            target.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            target.classList.remove('hidden');
        }
    });
}
