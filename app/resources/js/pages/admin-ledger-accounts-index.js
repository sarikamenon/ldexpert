import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';

async function initLedgerAccountsTable() {
    const table = document.getElementById('ledgerAccountsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        await initServerSideDataTable('#ledgerAccountsTable', dataUrl, {
            order: [[0, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 },
            ],
            getExtraData(d) {
                d.filter_type = table.getAttribute('data-filter-type') || 'schools';
                d.filter_search = '';
            },
        });
    } catch (error) {
        console.error('Failed to init ledger accounts table', error);
    }
}

async function initAllTransactionsTable() {
    const table = document.getElementById('allTransactionsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const defaultDateFrom = table.getAttribute('data-default-date-from') || '';
        const defaultDateTo   = table.getAttribute('data-default-date-to') || '';

        const dtInstance = await initServerSideDataTable('#allTransactionsTable', dataUrl, {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: [1, 2, 3, 4, 5, 6, 7] },
            ],
            getExtraData(d) {
                d.filter_date_from    = document.getElementById('atFilterDateFrom')?.value || defaultDateFrom;
                d.filter_date_to      = document.getElementById('atFilterDateTo')?.value || defaultDateTo;
                d.filter_direction    = document.getElementById('atFilterDirection')?.value || '';
                d.filter_school_id    = document.getElementById('atFilterSchool')?.value || '';
                d.filter_therapist_id = document.getElementById('atFilterTherapist')?.value || '';
            },
        });

        document.getElementById('atFilterApply')?.addEventListener('click', () => {
            if (dtInstance) dtInstance.ajax.reload();
        });

        document.getElementById('atFilterReset')?.addEventListener('click', () => {
            const dateFromEl  = document.getElementById('atFilterDateFrom');
            const dateToEl    = document.getElementById('atFilterDateTo');
            const directionEl = document.getElementById('atFilterDirection');
            const schoolEl    = document.getElementById('atFilterSchool');
            const therapistEl = document.getElementById('atFilterTherapist');

            if (dateFromEl)  dateFromEl.value  = defaultDateFrom;
            if (dateToEl)    dateToEl.value    = defaultDateTo;
            if (directionEl) directionEl.value = '';
            if (schoolEl)    schoolEl.value    = '';
            if (therapistEl) therapistEl.value = '';

            if (dtInstance) dtInstance.ajax.reload();
        });
    } catch (error) {
        console.error('Failed to init all transactions table', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ledgerAccountsTable')) {
        initLedgerAccountsTable();
    }

    if (document.getElementById('allTransactionsTable')) {
        initAllTransactionsTable();
    }
});

