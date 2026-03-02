import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

function getSelectedValues(selector) {
    const el = document.querySelector(selector);
    if (!el) return [];
    return Array.from(el.selectedOptions || el.querySelectorAll('option:checked'))
        .map(o => o.value)
        .filter(v => v !== '');
}

function getFilterData(d) {
    d.filter_school_ids = getSelectedValues('#school_ids');
    d.filter_therapist_ids = getSelectedValues('#therapist_ids');
    d.filter_service_ids = getSelectedValues('#service_ids');
}

async function initCaseloadTables() {
    const therapistTable = document.getElementById('caseloadTherapistTable');
    const unassignedTable = document.getElementById('caseloadUnassignedTable');
    if (!therapistTable && !unassignedTable) return;

    await loadDataTablesLibrary();

    const form = document.getElementById('caseloadFiltersForm');
    let therapistDt = null;
    let unassignedDt = null;

    if (therapistTable) {
        const dataUrl = therapistTable.getAttribute('data-datatable-url');
        if (dataUrl) {
            therapistDt = await initServerSideDataTable('#caseloadTherapistTable', dataUrl, {
                order: [[0, 'asc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [1] },
                ],
                getExtraData: getFilterData,
            });

            if (therapistDt && therapistDt.on) {
                therapistDt.on('xhr.dt', function (e, settings, json) {
                    if (!json) return;
                    const s = json.summary || {};
                    const el = (id) => document.getElementById(id);
                    if (el('metricTotalTherapists')) el('metricTotalTherapists').textContent = s.total_therapists || 0;
                    if (el('metricTotalActiveSSAs')) el('metricTotalActiveSSAs').textContent = s.total_active_ssas || 0;
                    if (el('metricMedianMinutes')) el('metricMedianMinutes').textContent = Number(s.median_minutes_per_week || 0).toLocaleString();
                    if (el('metricUnassignedSSAs')) el('metricUnassignedSSAs').textContent = json.unassignedCount || 0;
                });
            }
        }
    }

    if (unassignedTable) {
        const dataUrl = unassignedTable.getAttribute('data-datatable-url');
        if (dataUrl) {
            unassignedDt = await initServerSideDataTable('#caseloadUnassignedTable', dataUrl, {
                order: [[0, 'asc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [1, 2, 4] },
                ],
                getExtraData: getFilterData,
            });
        }
    }

    if (form && typeof window.jQuery !== 'undefined') {
        const reloadTables = () => {
            if (therapistDt) window.jQuery('#caseloadTherapistTable').DataTable().ajax.reload();
            if (unassignedDt) window.jQuery('#caseloadUnassignedTable').DataTable().ajax.reload();
        };
        form.addEventListener('change', reloadTables);
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            reloadTables();
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
                if (therapistDt) window.jQuery('#caseloadTherapistTable').DataTable().ajax.reload();
                if (unassignedDt) window.jQuery('#caseloadUnassignedTable').DataTable().ajax.reload();
            }
        });
    }
}

window.jQuery(function () {
    void initCaseloadTables();
});
