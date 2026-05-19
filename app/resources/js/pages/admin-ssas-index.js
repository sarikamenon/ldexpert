import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusChanges } from '../common/status-change';
import { setupAssignModal, setupUnassignModal } from '../common/ssa-modals';

async function initSSATable() {
    const table = document.getElementById('ssasTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    const form = document.getElementById('ssaFiltersForm');

    try {
        await loadDataTablesLibrary();
        await initServerSideDataTable('#ssasTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';

                const multiStatus = form.querySelector('select[name="statuses[]"]');
                if (multiStatus) {
                    d.filter_statuses = Array.from(multiStatus.selectedOptions).map((opt) => opt.value);
                    d.filter_status = '';
                } else {
                    const statusVal = form.querySelector('[name="status"]')?.value ?? '';
                    d.filter_status = statusVal === 'all' ? '' : statusVal;
                }

                d.filter_student_id = form.querySelector('[name="student_id"]')?.value ?? '';
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                d.filter_service_id = form.querySelector('[name="service_id"]')?.value ?? '';
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init SSAs table', error);
    }
}

function reloadDataTable() {
    if (typeof window.jQuery !== 'undefined') {
        const dt = window.jQuery('#ssasTable').DataTable();
        if (dt?.ajax?.reload) {
            dt.ajax.reload(null, false);
            return;
        }
    }
    window.location.reload();
}

function resetFiltersToDefaults(form) {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el instanceof HTMLInputElement) {
            const type = (el.type || '').toLowerCase();
            if (['hidden', 'submit', 'button', 'image', 'file'].includes(type)) return;
            if (type === 'checkbox' || type === 'radio') { el.checked = false; return; }
            el.value = '';
            return;
        }
        if (el instanceof HTMLTextAreaElement) { el.value = ''; return; }
        if (el instanceof HTMLSelectElement) {
            const defaults = (el.getAttribute('data-default-value') || '')
                .split(',').map((v) => v.trim()).filter(Boolean);
            if (el.multiple) {
                Array.from(el.options).forEach((opt) => {
                    opt.selected = defaults.includes(opt.value);
                });
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(el).val(defaults).trigger('change');
                }
            } else {
                const defaultVal = defaults[0] || '';
                el.value = defaultVal;
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(el).val(defaultVal).trigger('change');
                }
            }
        }
    });
}

function updateFiltersSummary() {
    const summary = document.getElementById('ssaFiltersSummary');
    if (!summary) return;

    const form = document.getElementById('ssaFiltersForm');
    if (!form) return;

    let count = 0;

    const statusEl = form.querySelector('select[name="statuses[]"]');
    if (statusEl) {
        const defaults = (statusEl.getAttribute('data-default-value') || '')
            .split(',')
            .map((v) => v.trim())
            .filter(Boolean)
            .sort();
        const current = Array.from(statusEl.selectedOptions)
            .map((opt) => opt.value)
            .sort();
        if (current.length !== defaults.length || current.some((v, i) => v !== defaults[i])) {
            count += 1;
        }
    } else {
        const singleStatus = form.querySelector('[name="status"]');
        if (singleStatus && singleStatus.value && singleStatus.value !== 'all') {
            count += 1;
        }
    }

    ['therapist_id', 'service_id', 'student_id', 'search'].forEach((name) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el && el.value) count += 1;
    });

    const label = summary.querySelector('[data-filter-count]');
    if (label) {
        label.textContent = `${count} ${count === 1 ? 'filter' : 'filters'} applied`;
    }
    summary.classList.toggle('hidden', count === 0);
    summary.classList.toggle('flex', count > 0);
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ssasTable')) {
        initSSATable();
    }

    const form = document.getElementById('ssaFiltersForm');
    if (form) {
        form.addEventListener('change', updateFiltersSummary);
        form.addEventListener('input', updateFiltersSummary);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reloadDataTable();
            updateFiltersSummary();
        });

        const clearAll = document.getElementById('ssaFiltersClearAll');
        if (clearAll) {
            clearAll.addEventListener('click', () => {
                resetFiltersToDefaults(form);
                reloadDataTable();
                updateFiltersSummary();
            });
        }

        updateFiltersSummary();
    }

    setupStatusChanges('ssa', '.change-status-btn', { idAttribute: 'ssa-id' });
    setupAssignModal({ onSuccess: reloadDataTable });
    setupUnassignModal({ onSuccess: reloadDataTable });
});
