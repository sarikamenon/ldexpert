/**
 * Invoice Create Page (Step 1) - Invoice details only.
 * Session logs are added in the next step (attach-sessions).
 */

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("createInvoiceForm");
    if (!form) {
        return;
    }
    // No session selection on this step; form submits to create draft
});
