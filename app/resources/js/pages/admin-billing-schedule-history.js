import {
    initServerSideDataTable,
    loadDataTablesLibrary,
} from "../common/datatables";

async function initHistoryTable() {
    const table = document.getElementById("billingScheduleHistoryTable");
    if (!table) return;

    const dataUrl = table.getAttribute("data-datatable-url");
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        await initServerSideDataTable("#billingScheduleHistoryTable", dataUrl, {
            order: [[0, "desc"]],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
        });
    } catch (error) {
        console.error("Failed to init billing schedule history table", error);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById("billingScheduleHistoryTable")) {
        return;
    }

    initHistoryTable();
});
