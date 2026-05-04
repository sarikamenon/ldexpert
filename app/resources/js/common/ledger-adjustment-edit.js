// Edit-modal flow: load an existing adjustment, then PUT changes.
import { errorAlert, successToast } from './sweetalert';
import {
    clearFieldErrors,
    closeAllAdjustmentModals,
    openModal,
    renderValidationErrors,
} from './ledger-adjustment-modals';

export function initEditAdjustmentFlow({ tableId, onAfterSave } = {}) {
    const table = tableId ? document.getElementById(tableId) : null;
    if (table) {
        // Delegated edit click — DataTables re-renders rows on every page/sort/filter change.
        table.addEventListener('click', (event) => {
            const editLink = event.target.closest('[data-edit-adjustment]');
            if (!editLink) {
                return;
            }
            event.preventDefault();
            void openEditModal(editLink.getAttribute('data-fetch-url'));
        });
    }

    initEditFormSubmit(onAfterSave);
}

async function openEditModal(fetchUrl) {
    if (!fetchUrl) {
        return;
    }

    try {
        const response = await fetch(fetchUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            await errorAlert(data.message || 'Could not load adjustment.');
            return;
        }

        const form = document.getElementById('editAdjustmentForm');
        if (!form || !data.entry) {
            return;
        }

        // GET show and PUT update share the same path; just point the form at it.
        form.action = fetchUrl;
        form.dataset.entryId = data.entry.id;

        document.getElementById('editAdjustmentRecordedAt').value = data.entry.recorded_at || '';
        document.getElementById('editAdjustmentAmount').value = data.entry.amount ?? '';
        document.getElementById('editAdjustmentNotes').value = data.entry.notes ?? '';

        clearFieldErrors(form);
        openModal('editAdjustmentModal');
    } catch (err) {
        console.error('Failed to load adjustment', err);
        await errorAlert('Could not load adjustment. Please try again.');
    }
}

function initEditFormSubmit(onAfterSave) {
    const form = document.getElementById('editAdjustmentForm');
    if (!form) {
        return;
    }

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
                method: 'POST', // Laravel reads _method=PUT from the form
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
                await errorAlert(data.message || 'Could not update adjustment.');
                return;
            }

            closeAllAdjustmentModals();
            await successToast(data.message || 'Adjustment updated.');
            if (typeof onAfterSave === 'function') {
                onAfterSave();
            }
        } catch (err) {
            console.error('Edit submit failed', err);
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
