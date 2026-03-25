/**
 * Attach sessions to therapist bill (Step 2).
 * jQuery for DOM; SweetAlert2 for confirm when selection would remove sessions.
 */

import $ from "jquery";
import { confirmDialog } from "../common/sweetalert";

document.addEventListener("DOMContentLoaded", function () {
    const $form = $("#attachSessionsForm");
    const $checkboxes = $(".session-log-checkbox");
    const $selectAllCheckbox = $("#selectAllCheckbox");
    const $selectAllBtn = $("#selectAllBtn");
    const $deselectAllBtn = $("#deselectAllBtn");
    const $summary = $("#sessionLogsSummary");
    const $selectedCount = $("#selectedCount");
    const $selectedTotal = $("#selectedTotal");

    const initialAttachedCount = $checkboxes.filter(":checked").length;

    function updateSummary() {
        const checked = $checkboxes.filter(":checked");
        const count = checked.length;

        let total = 0;
        checked.each(function () {
            total += parseFloat($(this).data("amount") || 0);
        });

        if ($selectedCount.length) {
            $selectedCount.text(count);
        }
        if ($selectedTotal.length) {
            $selectedTotal.text(total.toFixed(2));
        }

        if ($summary.length) {
            $summary.toggleClass("hidden", count === 0);
        }
    }

    function syncSelectAllCheckbox() {
        if (!$selectAllCheckbox.length) {
            return;
        }

        const total = $checkboxes.length;
        const checked = $checkboxes.filter(":checked").length;
        $selectAllCheckbox.prop("checked", total > 0 && checked === total);
    }

    if ($selectAllCheckbox.length) {
        $selectAllCheckbox.on("change", function () {
            $checkboxes.prop("checked", this.checked);
            updateSummary();
        });
    }

    if ($selectAllBtn.length) {
        $selectAllBtn.on("click", function () {
            $checkboxes.prop("checked", true);
            if ($selectAllCheckbox.length) {
                $selectAllCheckbox.prop("checked", true);
            }
            updateSummary();
        });
    }

    if ($deselectAllBtn.length) {
        $deselectAllBtn.on("click", function () {
            $checkboxes.prop("checked", false);
            if ($selectAllCheckbox.length) {
                $selectAllCheckbox.prop("checked", false);
            }
            updateSummary();
        });
    }

    $checkboxes.on("change", function () {
        updateSummary();
        syncSelectAllCheckbox();
    });

    $form.on("submit", async function (e) {
        const form = this;
        const selectedCount = $checkboxes.filter(":checked").length;

        const wouldRemoveSessions =
            selectedCount < initialAttachedCount ||
            (initialAttachedCount > 0 && selectedCount === 0);

        if (wouldRemoveSessions) {
            e.preventDefault();

            const removed = initialAttachedCount - selectedCount;
            const message =
                selectedCount === 0
                    ? "This will remove all sessions from the bill. Continue?"
                    : `This will remove ${removed} session(s) from the bill. Continue?`;

            const result = await confirmDialog({
                title: "Update sessions?",
                text: message,
                icon: "warning",
                confirmButtonText: "Yes, update",
                cancelButtonText: "Cancel",
            });

            if (!result.isConfirmed) {
                return;
            }

            form.submit();
        }
    });

    updateSummary();
    syncSelectAllCheckbox();
});

