import { initDataTable, initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { initLedgerAdjustmentForms } from '../common/ledger-adjustment-form';
import { successToast } from '../common/sweetalert';

window.jQuery(function ($) {
    initAdjustmentModals();
    initLedgerAdjustmentForms({
        async onSuccess(data) {
            closeAllAdjustmentModals();
            await successToast(data.message || 'Saved successfully.');
            window.location.reload();
        },
    });

    const table = document.getElementById('ledgerTransactionsTable') || document.querySelector('.ledger-transactions-table');
    if (!table) {
        return;
    }

    void (async function init() {
        await loadDataTablesLibrary();
        const dataUrl = table.getAttribute('data-datatable-url');
        if (dataUrl) {
            const selector = table.id ? `#${table.id}` : '.ledger-transactions-table';
            await initServerSideDataTable(selector, dataUrl, {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: [2, 3, 4, 5, 7] },
                ],
                getExtraData(d) {
                    d.filter_type = table.getAttribute('data-filter-type') || '';
                    d.filter_id = table.getAttribute('data-filter-id') || '';
                },
            });
        } else {
            await initDataTable('.ledger-transactions-table', {
                order: [[0, 'desc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: -1 },
                ],
            });
        }
    })();
});

function initAdjustmentModals() {
    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-open-modal');
            openModal(id);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-close-modal');
            closeModal(id);
        });
    });

    document.querySelectorAll('[data-ledger-adjustment-cancel]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('[data-ledger-adjustment-modal]');
            if (modal && modal.id) {
                closeModal(modal.id);
            }
        });
    });

    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllAdjustmentModals();
        }
    });
}

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    el.classList.remove('hidden');
    el.classList.add('flex');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    el.classList.add('hidden');
    el.classList.remove('flex');
}

function closeAllAdjustmentModals() {
    document.querySelectorAll('[data-ledger-adjustment-modal]').forEach((modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
}
