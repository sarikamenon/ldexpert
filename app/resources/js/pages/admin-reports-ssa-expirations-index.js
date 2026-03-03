import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

function getSelectedValues(selector) {
    const el = document.querySelector(selector);
    if (!el) return [];
    return Array.from(el.selectedOptions || el.querySelectorAll('option:checked'))
        .map(o => o.value)
        .filter(v => v !== '');
}

async function initExpirationTable() {
    const table = document.getElementById('expirationTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    await loadDataTablesLibrary();

    const form = document.getElementById('expirationFiltersForm');

    const dt = await initServerSideDataTable('#expirationTable', dataUrl, {
        order: [[5, 'asc']],
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: [7] },
        ],
        getExtraData(d) {
            if (!form) return;
            d.filter_expiration_window_days = form.querySelector('[name="expiration_window_days"]')?.value ?? '30';
            d.filter_school_ids = getSelectedValues('#school_ids');
            d.filter_therapist_ids = getSelectedValues('#therapist_ids');
            d.filter_bucket = form.querySelector('[name="bucket"]')?.value ?? 'upcoming';
        },
    });

    if (dt && dt.on) {
        dt.on('xhr.dt', function (e, settings, json) {
            if (!json || !json.summary) return;
            const s = json.summary;
            const el = (id) => document.getElementById(id);
            if (el('metricUpcoming')) el('metricUpcoming').textContent = s.upcoming_count || 0;
            if (el('metricExpired')) el('metricExpired').textContent = s.expired_count || 0;
            if (el('metricPending')) el('metricPending').textContent = s.pending_count || 0;
            if (el('metricNoCurrent')) el('metricNoCurrent').textContent = s.no_current_count || 0;
            if (el('metricUpcomingSubtext')) el('metricUpcomingSubtext').textContent = 'next ' + (s.expiration_window_days || 30) + ' days';
        });
    }

    if (form && typeof window.jQuery !== 'undefined') {
        const reloadTable = () => {
            const dataTable = window.jQuery('#expirationTable').DataTable();
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
            const bucketSelect = form.querySelector('[name="bucket"]');
            if (bucketSelect) bucketSelect.value = 'upcoming';
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery('#expirationTable').DataTable().ajax.reload();
            }
        });
    }
}

window.jQuery(function () {
    void initExpirationTable();
});
