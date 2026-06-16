import { initServerSideDataTable, loadDataTablesLibrary } from '../common/datatables';
import { setupStatusToggles } from '../common/status-change';
import { sendLoginDetails } from '../common/welcome-email';

async function initStudentsTable() {
    const table = document.getElementById('studentsTable');
    if (!table) return;

    const dataUrl = table.getAttribute('data-datatable-url');
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById('studentsFiltersForm');

        await initServerSideDataTable('#studentsTable', dataUrl, {
            // Column 0 is the selection checkbox; default sort by Name (column 2).
            order: [[2, 'asc']],
            pageLength: 25,
            columnDefs: [
                { targets: 0, orderable: false, className: 'select-col hidden' },
            ],
            getExtraData(d) {
                if (!form) return;
                d.filter_search = form.querySelector('[name="search"]')?.value ?? '';
                const statusVal = form.querySelector('[name="status"]')?.value ?? 'active';
                d.filter_status = statusVal === 'all' ? '' : statusVal;
                d.filter_school_id = form.querySelector('[name="school_id"]')?.value ?? '';
                d.filter_therapist_id = form.querySelector('[name="therapist_id"]')?.value ?? '';
            },
        });
    } catch (error) {
        console.error('Failed to init students table', error);
    }
}

function setupExportButton() {
    const button = document.getElementById('exportStudentsButton');
    const form = document.getElementById('studentsFiltersForm');
    if (!button || !form) {
        return;
    }

    button.addEventListener('click', (event) => {
        event.preventDefault();
        const url = new URL(button.href, window.location.origin);
        new FormData(form).forEach((value, key) => {
            if (value) {
                url.searchParams.set(key, value.toString());
            } else {
                url.searchParams.delete(key);
            }
        });
        window.location.href = url.toString();
    });
}

function setupMoreMenu() {
    const wrapper = document.getElementById('studentsMoreMenuWrapper');
    const button = document.getElementById('studentsMoreMenuButton');
    const menu = document.getElementById('studentsMoreMenu');
    if (!wrapper || !button || !menu) {
        return null;
    }

    const close = () => {
        menu.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    };
    const toggle = () => {
        const isOpen = !menu.classList.contains('hidden');
        if (isOpen) {
            close();
            return;
        }
        menu.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        toggle();
    });
    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    return { close };
}

function setupSelectionMode(moreMenu) {
    const enterButton = document.getElementById('welcomeEmailModeButton');
    const bar = document.getElementById('studentsSelectionBar');
    const countEl = document.getElementById('studentsSelectedCount');
    const cancelButton = document.getElementById('cancelWelcomeEmailButton');
    const sendButton = document.getElementById('sendWelcomeEmailButton');
    const selectAll = document.getElementById('studentsSelectAll');
    const table = document.getElementById('studentsTable');
    if (!enterButton || !bar || !countEl || !cancelButton || !sendButton || !table) {
        return;
    }

    const selectCells = () => table.querySelectorAll('.select-col');
    const checkboxes = () => table.querySelectorAll('.student-select');

    const updateCount = () => {
        const selected = table.querySelectorAll('.student-select:checked').length;
        countEl.textContent = String(selected);
    };

    const showColumn = (visible) => {
        selectCells().forEach((cell) => cell.classList.toggle('hidden', !visible));
    };

    const enter = () => {
        showColumn(true);
        bar.classList.remove('hidden');
        bar.classList.add('flex');
        moreMenu?.close();
    };

    const exit = () => {
        showColumn(false);
        bar.classList.add('hidden');
        bar.classList.remove('flex');
        if (selectAll) selectAll.checked = false;
        checkboxes().forEach((cb) => {
            cb.checked = false;
        });
        updateCount();
    };

    enterButton.addEventListener('click', enter);
    cancelButton.addEventListener('click', exit);

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes().forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            updateCount();
        });
    }

    // Checkboxes are re-rendered by DataTables on each draw, so delegate.
    table.addEventListener('change', (event) => {
        if (event.target.classList.contains('student-select')) {
            updateCount();
        }
    });

    sendButton.addEventListener('click', async () => {
        const ids = Array.from(table.querySelectorAll('.student-select:checked')).map((cb) => cb.value);
        const sent = await sendLoginDetails(sendButton.dataset.url, ids);
        if (sent) {
            exit();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('studentsTable')) {
        return;
    }

    initStudentsTable();
    setupStatusToggles('student', '.toggle-student-status', { idAttribute: 'student' });
    setupExportButton();
    const moreMenu = setupMoreMenu();
    setupSelectionMode(moreMenu);

    // Reload table when filters change so server receives filter_search, filter_status, filter_school_id
    const form = document.getElementById('studentsFiltersForm');
    if (form) {
        form.addEventListener('change', () => {
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#studentsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof window.jQuery !== 'undefined') {
                const dt = window.jQuery('#studentsTable').DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            }
        });
    }
});


