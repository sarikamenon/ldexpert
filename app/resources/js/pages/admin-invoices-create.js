/**
 * Invoice Create Page (Step 1) - Invoice details only.
 * Session logs are added in the next step (attach-sessions).
 */

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("createInvoiceForm");
    if (!form) {
        return;
    }

    // Recompute the due date from the invoice date using the selected
    // school/family's payment terms (falling back to the billing-settings
    // default when no school is selected). Stops once the user edits the due
    // date manually, so their choice is never overwritten.
    const schoolSelect = document.getElementById("school_id");
    const invoiceDate = document.getElementById("invoice_date");
    const dueDate = document.getElementById("due_date");
    const termsLabel = document.querySelector("[data-due-date-terms]");

    if (!invoiceDate || !dueDate) {
        return;
    }

    const defaultTermsDays =
        parseInt(invoiceDate.dataset.paymentTermsDays, 10) || 0;
    let dueDateEdited = false;

    function currentTermsDays() {
        const selected = schoolSelect?.selectedOptions?.[0];
        const fromSchool = parseInt(
            selected?.dataset?.paymentTermsDays ?? "",
            10
        );
        return Number.isNaN(fromSchool) ? defaultTermsDays : fromSchool;
    }

    function recomputeDueDate() {
        const termsDays = currentTermsDays();

        if (termsLabel) {
            termsLabel.textContent = String(termsDays);
        }

        if (dueDateEdited || !invoiceDate.value) {
            return;
        }

        const base = new Date(invoiceDate.value);
        base.setDate(base.getDate() + termsDays);
        dueDate.value = base.toISOString().slice(0, 10);
    }

    dueDate.addEventListener("input", function () {
        dueDateEdited = true;
    });

    invoiceDate.addEventListener("change", recomputeDueDate);

    // The school dropdown is a Select2 widget, which fires its change through
    // jQuery — a native addEventListener won't catch it. Bind via jQuery when
    // available, falling back to native for a plain <select>.
    if (schoolSelect) {
        if (typeof window.jQuery !== "undefined") {
            window.jQuery(schoolSelect).on("change", recomputeDueDate);
        } else {
            schoolSelect.addEventListener("change", recomputeDueDate);
        }
    }
});
