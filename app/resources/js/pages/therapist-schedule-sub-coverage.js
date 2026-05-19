import { confirmDialog, errorAlert, successToast, showLoading, closeAlert } from '../common/sweetalert';
import { buildPickerState, fetchEligibleSubs } from '../common/sub-picker';

// Picker state + fetch logic moved to ../common/sub-picker.js; this file now
// only handles the three page-level integrations (create form, edit form,
// coverage panel) plus the cancel-button handler.


// ── Create-form integration ───────────────────────────────────────────────────

function initCreateFormPicker() {
    const pickerRoot = document.getElementById('sub_invitee_picker');
    if (!pickerRoot) return;

    // The edit-mode "no open request" branch reuses the same picker DOM ids but
    // is owned by initEditCheckboxToggle/loadEditPickerOnce. Bail out so we
    // don't attach a second trigger handler that toggles the dropdown twice
    // (which causes the open-then-immediately-close flicker).
    if (!document.getElementById('request_sub')) return;

    const triggerEl = document.getElementById('sub_picker_trigger');
    const dropdownEl = document.getElementById('sub_picker_dropdown');
    const searchEl = /** @type {HTMLInputElement|null} */ (document.getElementById('sub_picker_search'));
    const listEl = document.getElementById('sub_picker_list');
    const placeholderEl = document.getElementById('sub_picker_placeholder');
    const hiddenInputsEl = document.getElementById('sub_invitee_inputs');

    if (!triggerEl || !dropdownEl || !searchEl || !listEl || !placeholderEl || !hiddenInputsEl) return;

    const baseUrl = pickerRoot.getAttribute('data-eligible-subs-url') ?? '';

    const picker = buildPickerState({
        triggerEl,
        dropdownEl,
        searchEl,
        listEl,
        placeholderEl,
        hiddenInputsEl,
        inputName: 'sub_invitee_ids[]',
    });

    async function maybeRefreshPicker() {
        const checkbox = document.getElementById('request_sub');
        if (!checkbox?.checked) return;

        const serviceId = /** @type {HTMLSelectElement|null} */ (document.getElementById('service_id'))?.value;
        const scheduleDate = /** @type {HTMLInputElement|null} */ (document.getElementById('schedule_date'))?.value;

        if (!serviceId || !scheduleDate) {
            picker.setOptions([], []);
            const placeholder = triggerEl.querySelector('.picker-placeholder');
            if (placeholder) {
                placeholder.textContent = 'Select a service and date above first.';
                placeholder.style.display = '';
            }
            return;
        }

        picker.setLoading();

        try {
            const url = `${baseUrl}?service_id=${encodeURIComponent(serviceId)}&date=${encodeURIComponent(scheduleDate)}`;
            const subs = await fetchEligibleSubs(url);
            picker.setOptions(subs);
        } catch {
            picker.setError('Could not load eligible therapists. Please try again.');
        }
    }

    document.getElementById('request_sub')?.addEventListener('change', maybeRefreshPicker);
    document.getElementById('schedule_date')?.addEventListener('change', maybeRefreshPicker);

    // service_id uses Select2 which fires jQuery change events, not native ones
    const bindServiceChange = () => {
        const serviceEl = document.getElementById('service_id');
        if (!serviceEl) return;
        if (window.jQuery) {
            window.jQuery(serviceEl).on('change', maybeRefreshPicker);
        } else {
            setTimeout(bindServiceChange, 50);
        }
    };
    bindServiceChange();

    if (/** @type {HTMLInputElement|null} */ (document.getElementById('request_sub'))?.checked) {
        maybeRefreshPicker();
    }
}

// ── Edit-form / sub-coverage panel integration ────────────────────────────────

function initCoveragePanelPicker() {
    const pickerRoot = document.getElementById('coverage_invitee_picker');
    if (!pickerRoot) return;

    const triggerEl = document.getElementById('coverage_picker_trigger');
    const dropdownEl = document.getElementById('coverage_picker_dropdown');
    const searchEl = /** @type {HTMLInputElement|null} */ (document.getElementById('coverage_picker_search'));
    const listEl = document.getElementById('coverage_picker_list');
    const placeholderEl = document.getElementById('coverage_picker_placeholder');
    const hiddenInputsEl = document.getElementById('coverage_invitee_inputs');

    if (!triggerEl || !dropdownEl || !searchEl || !listEl || !hiddenInputsEl) return;

    const url = pickerRoot.getAttribute('data-eligible-subs-url') ?? '';
    if (!url) return;

    const inputName = pickerRoot.getAttribute('data-input-name') || 'sub_invitee_ids[]';
    const dummyDiv = document.createElement('div');

    const picker = buildPickerState({
        triggerEl,
        dropdownEl,
        searchEl,
        listEl,
        placeholderEl: placeholderEl ?? dummyDiv,
        hiddenInputsEl,
        inputName,
    });

    fetchEligibleSubs(url)
        .then((subs) => picker.setOptions(subs))
        .catch(() => picker.setError('Could not load eligible therapists.'));

    // @ts-ignore
    pickerRoot._picker = picker;
}

/**
 * Wires the edit-mode "Request a sub" checkbox to show/hide the inline form
 * and lazily loads the eligible-subs picker on first reveal.
 * The URL already encodes schedule context so no service/date params are needed.
 */
function initEditCheckboxToggle() {
    const checkbox = /** @type {HTMLInputElement|null} */ (document.getElementById('edit_request_sub'));
    const container = document.getElementById('sub_reason_container');
    if (!checkbox || !container) return;

    checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
            container.classList.remove('hidden');
            loadEditPickerOnce();
        } else {
            container.classList.add('hidden');
        }
    });

    if (checkbox.checked) {
        container.classList.remove('hidden');
        loadEditPickerOnce();
    }
}

function loadEditPickerOnce() {
    const pickerRoot = document.getElementById('sub_invitee_picker');
    if (!pickerRoot || pickerRoot.dataset.loaded) return;
    pickerRoot.dataset.loaded = '1';

    const url = pickerRoot.getAttribute('data-eligible-subs-url') ?? '';
    const triggerEl = document.getElementById('sub_picker_trigger');
    const dropdownEl = document.getElementById('sub_picker_dropdown');
    const searchEl = /** @type {HTMLInputElement|null} */ (document.getElementById('sub_picker_search'));
    const listEl = document.getElementById('sub_picker_list');
    const placeholderEl = document.getElementById('sub_picker_placeholder');
    const hiddenInputsEl = document.getElementById('sub_invitee_inputs');

    if (!url || !triggerEl || !dropdownEl || !searchEl || !listEl || !hiddenInputsEl) return;

    const dummyDiv = document.createElement('div');
    const picker = buildPickerState({
        triggerEl,
        dropdownEl,
        searchEl,
        listEl,
        placeholderEl: placeholderEl ?? dummyDiv,
        hiddenInputsEl,
        inputName: 'sub_invitee_ids[]',
    });

    fetchEligibleSubs(url)
        .then((subs) => picker.setOptions(subs))
        .catch(() => picker.setError('Could not load eligible therapists.'));

    // @ts-ignore
    pickerRoot._picker = picker;
}

function bindPanelCancelHandler() {
    document.body.addEventListener('click', async (event) => {
        const button = /** @type {HTMLElement} */ (event.target).closest('button[data-cancel-url]');
        if (!button) return;

        event.preventDefault();

        const result = await confirmDialog({
            title: 'Withdraw Sub Request?',
            text: 'This will withdraw the coverage request. It can be re-submitted from the schedule.',
            icon: 'warning',
            confirmButtonText: 'Yes, withdraw',
        });

        if (!result.isConfirmed) return;

        showLoading('Withdrawing sub request...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = button.getAttribute('data-cancel-url') ?? '';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });

            closeAlert();

            if (!response.ok) {
                const data = await response.json().catch(() => ({ message: 'Failed to withdraw sub request.' }));
                throw new Error(data.message ?? 'Failed to withdraw sub request.');
            }

            await successToast('Sub request withdrawn.');
            window.location.reload();
        } catch (err) {
            errorAlert(err instanceof Error ? err.message : 'An error occurred.');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCreateFormPicker();
    initEditCheckboxToggle();
    initCoveragePanelPicker();
    bindPanelCancelHandler();
});
