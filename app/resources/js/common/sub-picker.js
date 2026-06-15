// Reusable pill-style multi-select picker for "invite a sub" flows.
// Used from the schedule create form, the schedule edit form, and the
// sub-coverage panel — all three render the same DOM shape and need the
// same selection / dropdown / search behavior.

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
export function buildPickerState(els) {
    /** @type {SubOption[]} */
    let allOptions = [];
    /** @type {Set<number>} */
    const selected = new Set();

    function render() {
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

        const search = els.searchEl.value.toLowerCase();
        els.listEl.innerHTML = '';
        const filtered = allOptions.filter((o) => o.name.toLowerCase().includes(search));

        if (filtered.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-xs text-foreground/50 italic px-3 py-2';
            empty.textContent = search ? 'No results.' : 'No eligible therapists found.';
            els.listEl.appendChild(empty);
        }

        // Declined invitees are excluded from bulk select: re-adding a declined
        // ID re-invites them (and fires a fresh invitation email) on submit, so
        // "Select all" must not sweep them in silently.
        const selectable = filtered.filter((o) => o.invitee_status !== 'declined');

        if (selectable.length > 0) {
            const allSelected = selectable.every((o) => selected.has(o.id));

            const selectAll = document.createElement('label');
            selectAll.className = 'flex items-center gap-2.5 px-3 py-2 rounded-md cursor-pointer text-sm font-medium border-b border-border mb-1 transition-colors hover:bg-muted/50 text-foreground';
            selectAll.setAttribute('role', 'option');
            selectAll.setAttribute('aria-selected', String(allSelected));

            const selectAllCb = document.createElement('input');
            selectAllCb.type = 'checkbox';
            selectAllCb.checked = allSelected;
            selectAllCb.className = 'h-4 w-4 rounded border-border text-primary focus:ring-primary shrink-0';
            selectAllCb.setAttribute('aria-label', 'Select all therapists');

            const selectAllSpan = document.createElement('span');
            selectAllSpan.className = 'flex-1';
            selectAllSpan.textContent = allSelected ? 'Deselect all' : 'Select all';

            selectAllCb.addEventListener('change', () => {
                if (selectAllCb.checked) {
                    selectable.forEach((o) => selected.add(o.id));
                } else {
                    selectable.forEach((o) => selected.delete(o.id));
                }
                syncHiddenInputs();
                render();
            });

            selectAll.appendChild(selectAllCb);
            selectAll.appendChild(selectAllSpan);
            els.listEl.appendChild(selectAll);
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

    els.searchEl.addEventListener('input', render);

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
        els.dropdownEl.classList.remove('bottom-full', 'mb-1');
        els.dropdownEl.classList.add('mt-1');
        els.dropdownEl.style.removeProperty('top');

        const triggerRect = els.triggerEl.getBoundingClientRect();
        const dropdownHeight = els.dropdownEl.offsetHeight;
        const spaceBelow = window.innerHeight - triggerRect.bottom;
        const spaceAbove = triggerRect.top;

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

/**
 * Fetches eligible subs from the picker endpoint. Returns [] on any non-array
 * payload so callers can pipe straight into setOptions.
 *
 * @param {string} url
 * @returns {Promise<SubOption[]>}
 */
export async function fetchEligibleSubs(url) {
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
