import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

function getSelectedValues(selector) {
    const el = document.querySelector(selector);
    if (!el) return [];
    return Array.from(el.selectedOptions || el.querySelectorAll('option:checked'))
        .map(o => o.value)
        .filter(v => v !== '');
}

async function initUtilizationTable() {
    const table = document.getElementById('utilizationTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();

    const form = document.getElementById('utilizationFiltersForm');

    const dt = await initServerSideDataTable('#utilizationTable', dataUrl, {
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: [] },
        ],
        getExtraData(d) {
            if (!form) return;
            d.filter_start_date = form.querySelector('[name="start_date"]')?.value ?? '';
            d.filter_end_date = form.querySelector('[name="end_date"]')?.value ?? '';
            d.filter_school_ids = getSelectedValues('#school_ids');
            d.filter_therapist_ids = getSelectedValues('#therapist_ids');
            d.filter_service_ids = getSelectedValues('#service_ids');
        },
    });

    if (dt && dt.on) {
        dt.on('xhr.dt', function (e, settings, json) {
            if (!json || !json.summary) return;
            const s = json.summary;
            const el = (id) => document.getElementById(id);
            if (el('metricTotalTho')) el('metricTotalTho').textContent = Number(s.total_tho_minutes || 0).toLocaleString();
            if (el('metricTotalServed')) el('metricTotalServed').textContent = Number(s.total_served_minutes || 0).toLocaleString();
            if (el('metricUtilization')) el('metricUtilization').textContent = Number(s.overall_utilization_percent || 0).toFixed(1) + '%';
            if (el('metricUnderServed')) el('metricUnderServed').textContent = s.under_served_count || 0;
        });
    }

    if (form && typeof window.jQuery !== 'undefined') {
        const reloadTable = () => {
            const dataTable = window.jQuery('#utilizationTable').DataTable();
            if (dataTable && dataTable.ajax && dataTable.ajax.reload) {
                dataTable.ajax.reload();
            }
        };
        form.addEventListener('change', reloadTable);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reloadTable();
        });
    }

    const resetBtn = document.getElementById('resetFiltersBtn');
    if (resetBtn && form) {
        resetBtn.addEventListener('click', () => {
            form.reset();
            const selects = form.querySelectorAll('select[multiple]');
            selects.forEach(s => {
                Array.from(s.options).forEach(o => { o.selected = false; });
                s.dispatchEvent(new Event('change', { bubbles: true }));
            });
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery('#utilizationTable').DataTable().ajax.reload();
            }
        });
    }
}

window.jQuery(function () {
    void initUtilizationTable();
});
