import {
    initServerSideDataTable,
    loadDataTablesLibrary,
} from "../common/datatables";

async function initSchedulesTable() {
    const table = document.getElementById("billingSchedulesTable");
    if (!table) return;

    const dataUrl = table.getAttribute("data-datatable-url");
    if (!dataUrl) return;

    try {
        await loadDataTablesLibrary();

        const form = document.getElementById("billingScheduleFiltersForm");

        await initServerSideDataTable("#billingSchedulesTable", dataUrl, {
            order: [[4, "asc"]],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: -1 }],
            getExtraData(d) {
                if (!form) return;
                d.filter_schedule_type =
                    form.querySelector('[name="schedule_type"]')?.value ?? "";
                d.filter_billing_mode =
                    form.querySelector('[name="billing_mode"]')?.value ?? "";
                d.filter_is_active =
                    form.querySelector('[name="is_active"]')?.value ?? "";
            },
        });

        if (form && typeof window.jQuery !== "undefined") {
            form.addEventListener("change", () => {
                const dt = window.jQuery("#billingSchedulesTable").DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });

            form.addEventListener("submit", (e) => {
                e.preventDefault();
                const dt = window.jQuery("#billingSchedulesTable").DataTable();
                if (dt && dt.ajax && dt.ajax.reload) {
                    dt.ajax.reload();
                }
            });
        }
    } catch (error) {
        console.error("Failed to init billing schedules table", error);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById("billingSchedulesTable")) {
        return;
    }

    initSchedulesTable();
});
