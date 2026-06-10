import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

function reloadScheduleTable() {
    if (typeof window.jQuery === 'undefined') return;
    const dt = window.jQuery('#schedulesTable').DataTable();
    if (dt && dt.ajax && dt.ajax.reload) dt.ajax.reload();
}

function updateScheduleFiltersSummary() {
    const summary = document.getElementById('scheduleFiltersSummary');
    const form = document.getElementById('scheduleFiltersForm');
    if (!summary || !form) return;

    let count = 0;

    // The date range always filters the list (defaults to today → today + 7),
    // so count it whenever either bound has a value.
    const dateFrom = form.querySelector('[name="date_from"]');
    const dateTo = form.querySelector('[name="date_to"]');
    if (dateFrom?.value || dateTo?.value) count += 1;

    ['status', 'billing_status', 'ssa_id', 'therapist_id'].forEach((name) => {
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

function resetScheduleFiltersToDefaults(form) {
    form.querySelectorAll('input, select').forEach((el) => {
        if (el instanceof HTMLInputElement) {
            const type = (el.type || '').toLowerCase();
            if (['hidden', 'submit', 'button'].includes(type)) return;
            el.value = el.getAttribute('data-default-value') ?? '';
            return;
        }
        if (el instanceof HTMLSelectElement) {
            el.value = '';
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(el).val('').trigger('change');
            }
        }
    });
}

function setupScheduleFilters() {
    const form = document.getElementById('scheduleFiltersForm');
    if (!form) return;

    const clearAll = document.getElementById('scheduleFiltersClearAll');
    if (clearAll) {
        clearAll.addEventListener('click', () => {
            resetScheduleFiltersToDefaults(form);
            reloadScheduleTable();
            updateScheduleFiltersSummary();
        });
    }

    // Select2 fires jQuery change events; bridge them to the summary counter.
    // Table reload is handled by the form's native 'change' listener (Select2 change bubbles to it).
    if (typeof window.jQuery !== 'undefined') {
        window.jQuery(form).find('select').on('change', updateScheduleFiltersSummary);
    }

    updateScheduleFiltersSummary();
}

async function initScheduleTable() {
    const table = document.getElementById('schedulesTable');
    if (!table) return;

    try {
        await loadDataTablesLibrary();
        const dataUrl = table.getAttribute('data-datatable-url');
        if (dataUrl) {
            const form = document.getElementById(table.getAttribute('data-filter-form') || 'scheduleFiltersForm');
            await initServerSideDataTable('#schedulesTable', dataUrl, {
                order: [[0, 'asc']],
                pageLength: 25,
                getExtraData(d) {
                    d.filter_student_id = table.getAttribute('data-student-id') || '';
                    if (!form) return;
                    d.filter_date_from = form.querySelector('[name="date_from"]')?.value ?? '';
                    d.filter_date_to = form.querySelector('[name="date_to"]')?.value ?? '';
                    d.filter_status = form.querySelector('[name="status"]')?.value ?? '';
                    d.filter_billing_status = form.querySelector('[name="billing_status"]')?.value ?? '';
                    d.filter_ssa_id = form.querySelector('[name="ssa_id"]')?.value ?? '';
                    d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
                },
            });
            if (form) {
                form.addEventListener('change', () => {
                    reloadScheduleTable();
                    updateScheduleFiltersSummary();
                });
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    reloadScheduleTable();
                    updateScheduleFiltersSummary();
                });
            }
            return;
        }
        const $tableRow = $('#schedulesTable tbody tr');
        if (!$tableRow.length || $tableRow.find('td[colspan]').length) {
            return;
        }
        await initDataTable('#schedulesTable', {
            order: [[0, 'asc']],
            paging: false,
            info: false,
            searching: false,
        });
    } catch (error) {
        console.error('Failed to init schedules table', error);
    }
}

$(document).ready(function () {
    initScheduleTable();
    setupScheduleFilters();
});

