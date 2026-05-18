import { confirmDialog, errorAlert, successToast, showLoading, closeAlert } from '../common/sweetalert';

// ── Multi-select dropdown helper ──────────────────────────────────────────────

/**
 * @typedef {{ id: number, name: string, invitee_status: string }} SubOption
 */

/**
 * Builds and manages a pill-style multi-select dropdown for eligible subs.
 *
 * @param {{
 *   triggerEl: HTMLElement,
 *   dropdownEl: HTMLElement,
 *   searchEl: HTMLInputElement,
 *   listEl: HTMLElement,
 *   placeholderEl: HTMLElement,
 *   hiddenInputsEl: HTMLElement,
 *   inputName: string,
 * }} els
 */
function buildPickerState(els) {
    /** @type {SubOption[]} */
    let allOptions = [];
    /** @type {Set<number>} */
    const selected = new Set();

    function render() {
        // Pills inside trigger
        const pills = els.triggerEl.querySelectorAll('.picker-pill');
        pills.forEach((p) => p.remove());

        const placeholder = els.triggerEl.querySelector('.picker-placeholder');
        if (selected.size === 0) {
            if (placeholder) placeholder.style.display = '';
        } else {
            if (placeholder) placeholder.style.display = 'none';
            selected.forEach((id) => {
                const opt = allOptions.find((o) => o.id === id);
                if (!opt) return;
                const pill = document.createElement('span');
                pill.className = 'picker-pill inline-flex items-center gap-1 rounded-full bg-primary/10 text-primary text-xs font-medium px-2.5 py-1';
                pill.dataset.id = String(id);
                pill.innerHTML = `${opt.name}<button type="button" class="pill-remove ml-0.5 text-primary/60 hover:text-primary leading-none" aria-label="Remove ${opt.name}">&times;</button>`;
                els.triggerEl.insertBefore(pill, placeholder ?? null);
            });
        }

        // Dropdown list
        const search = els.searchEl.value.toLowerCase();
        els.listEl.innerHTML = '';
        const filtered = allOptions.filter((o) => o.name.toLowerCase().includes(search));

        if (filtered.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-xs text-foreground/50 italic px-3 py-2';
            empty.textContent = search ? 'No results.' : 'No eligible therapists found.';
            els.listEl.appendChild(empty);
        }

        filtered.forEach((opt) => {
            const isChecked = selected.has(opt.id);
            const isDeclined = opt.invitee_status === 'declined';

            const item = document.createElement('label');
            item.className = `flex items-center gap-2.5 px-3 py-2 rounded-md cursor-pointer text-sm transition-colors ${isChecked ? 'bg-primary/5 text-foreground' : 'hover:bg-muted/50 text-foreground'}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(isChecked));

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = String(opt.id);
            cb.checked = isChecked;
            cb.className = 'h-4 w-4 rounded border-border text-primary focus:ring-primary shrink-0';
            cb.setAttribute('aria-label', opt.name);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'flex-1 truncate';
            nameSpan.textContent = opt.name;

            item.appendChild(cb);
            item.appendChild(nameSpan);

            if (isDeclined) {
                const tag = document.createElement('span');
                tag.className = 'text-xs text-foreground/40 italic shrink-0';
                tag.textContent = 'Declined';
                item.appendChild(tag);
            }

            cb.addEventListener('change', () => {
                if (cb.checked) {
                    selected.add(opt.id);
                } else {
                    selected.delete(opt.id);
                }
                syncHiddenInputs();
                render();
            });

            els.listEl.appendChild(item);
        });
    }

    function syncHiddenInputs() {
        els.hiddenInputsEl.innerHTML = '';
        selected.forEach((id) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = els.inputName;
            hidden.value = String(id);
            els.hiddenInputsEl.appendChild(hidden);
        });
    }

    // Pill remove via event delegation
    els.triggerEl.addEventListener('click', (e) => {
        const removeBtn = /** @type {HTMLElement} */ (e.target).closest('.pill-remove');
        if (removeBtn) {
            e.stopPropagation();
            const pill = removeBtn.closest('.picker-pill');
            const id = pill ? Number(pill.dataset.id) : NaN;
            if (!isNaN(id)) {
                selected.delete(id);
                syncHiddenInputs();
                render();
            }
            return;
        }
        e.stopPropagation();
        toggleDropdown();
    });

    // Search filter
    els.searchEl.addEventListener('input', render);

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!els.triggerEl.closest('.relative')?.contains(/** @type {Node} */ (e.target))) {
            closeDropdown();
        }
    });

    function toggleDropdown() {
        const isOpen = !els.dropdownEl.classList.contains('hidden');
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }

    function openDropdown() {
        els.dropdownEl.classList.remove('hidden');
        els.triggerEl.setAttribute('aria-expanded', 'true');
        positionDropdown();
        els.searchEl.focus();
        render();
    }

    function closeDropdown() {
        els.dropdownEl.classList.add('hidden');
        els.triggerEl.setAttribute('aria-expanded', 'false');
    }

    function positionDropdown() {
        // Reset to default (open downward) before measuring.
        els.dropdownEl.classList.remove('bottom-full', 'mb-1');
        els.dropdownEl.classList.add('mt-1');
        els.dropdownEl.style.removeProperty('top');

        const triggerRect = els.triggerEl.getBoundingClientRect();
        const dropdownHeight = els.dropdownEl.offsetHeight;
        const spaceBelow = window.innerHeight - triggerRect.bottom;
        const spaceAbove = triggerRect.top;

        // Flip up if there isn't enough room below but there is above.
        if (spaceBelow < dropdownHeight + 8 && spaceAbove > spaceBelow) {
            els.dropdownEl.classList.remove('mt-1');
            els.dropdownEl.classList.add('bottom-full', 'mb-1');
        }
    }

    return {
        /**
         * @param {SubOption[]} options
         * @param {number[]} preSelectedIds
         */
        setOptions(options, preSelectedIds = []) {
            allOptions = options;
            selected.clear();
            preSelectedIds.forEach((id) => selected.add(id));
            // Also pre-select from invitee_status
            options.forEach((o) => {
                if (o.invitee_status === 'selected') selected.add(o.id);
            });
            const placeholder = els.triggerEl.querySelector('.picker-placeholder');
            if (placeholder) {
                placeholder.textContent = 'Add therapist…';
                placeholder.classList.remove('text-danger');
            }
            syncHiddenInputs();
            render();
        },
        setLoading(msg = 'Loading eligible therapists…') {
            const placeholder = els.triggerEl.querySelector('.picker-placeholder');
            if (placeholder) {
                placeholder.textContent = msg;
                placeholder.style.display = '';
            }
        },
        setError(msg) {
            const placeholder = els.triggerEl.querySelector('.picker-placeholder');
            if (placeholder) {
                placeholder.textContent = msg;
                placeholder.classList.add('text-danger');
                placeholder.style.display = '';
            }
        },
        getSelectedIds() {
            return [...selected];
        },
    };
}

// ── Fetch helper ──────────────────────────────────────────────────────────────

/**
 * @param {string} url
 * @returns {Promise<SubOption[]>}
 */
async function fetchEligibleSubs(url) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const response = await fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    });
    if (!response.ok) throw new Error('Failed to load eligible therapists.');
    const data = await response.json();
    return Array.isArray(data) ? data : [];
}

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
