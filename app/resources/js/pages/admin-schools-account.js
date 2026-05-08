import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('schoolAccountTable');
    if (!table) {
        return;
    }

    void initAccountTable(table);
    bindFilters(table);
});

async function initAccountTable(table) {
    await loadDataTablesLibrary();

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) {
        return;
    }

    await initServerSideDataTable('#schoolAccountTable', dataUrl, {
        order: [],
        ordering: false,
        pageLength: 25,
        columnDefs: [
            { orderable: false, targets: '_all' },
        ],
        language: {
            emptyTable: 'No account activity yet.',
            zeroRecords: 'No matching records found.',
        },
        getExtraData(d) {
            const fromInput = document.getElementById('filter_date_from');
            const toInput = document.getElementById('filter_date_to');
            d.filter_date_from = fromInput ? fromInput.value : '';
            d.filter_date_to = toInput ? toInput.value : '';
        },
    });
}

function bindFilters(table) {
    const form = document.getElementById('schoolAccountFilters');
    if (!form) {
        return;
    }

    const reload = () => {
        const dt = window.jQuery && window.jQuery.fn.DataTable
            ? window.jQuery('#schoolAccountTable').DataTable()
            : null;
        if (dt) {
            dt.ajax.reload();
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        reload();
    });

    const resetBtn = document.getElementById('schoolAccountResetFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            const fromInput = document.getElementById('filter_date_from');
            const toInput = document.getElementById('filter_date_to');
            if (fromInput) {
                fromInput.value = table.getAttribute('data-default-from') || '';
            }
            if (toInput) {
                toInput.value = table.getAttribute('data-default-to') || '';
            }
            reload();
        });
    }
}
