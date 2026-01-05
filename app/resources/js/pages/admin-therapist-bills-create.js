/**
 * Therapist Bill Create Page JavaScript
 * Handles session log selection, select all/deselect all, summary calculation,
 * and validation to ensure all selected sessions belong to the selected therapist
 */

import { errorAlert } from "../common/sweetalert";

document.addEventListener("DOMContentLoaded", function () {
    const selectAllCheckbox = document.getElementById("selectAllCheckbox");
    const selectAllBtn = document.getElementById("selectAllBtn");
    const deselectAllBtn = document.getElementById("deselectAllBtn");
    const checkboxes = document.querySelectorAll(".session-log-checkbox");
    const summary = document.getElementById("sessionLogsSummary");
    const selectedCount = document.getElementById("selectedCount");
    const selectedTotal = document.getElementById("selectedTotal");
    const form = document.getElementById("createBillForm");
    const therapistSelect = document.getElementById("therapist_id");

    if (!form) {
        return;
    }

    function updateSummary() {
        const selected = Array.from(checkboxes).filter((cb) => cb.checked);
        const count = selected.length;
        const total = selected.reduce(
            (sum, cb) => sum + parseFloat(cb.dataset.amount || 0),
            0
        );

        if (selectedCount) {
            selectedCount.textContent = count;
        }
        if (selectedTotal) {
            selectedTotal.textContent = total.toFixed(2);
        }
        if (summary) {
            summary.classList.toggle("hidden", count === 0);
        }
    }

    function validateTherapistSelection() {
        if (!therapistSelect || !therapistSelect.value) {
            return;
        }

        const selectedTherapistId = parseInt(therapistSelect.value, 10);
        const invalidCheckboxes = Array.from(checkboxes).filter((cb) => {
            if (!cb.checked) {
                return false;
            }
            const sessionTherapistId = parseInt(cb.dataset.therapistId || "0", 10);
            return sessionTherapistId !== selectedTherapistId;
        });

        if (invalidCheckboxes.length > 0) {
            // Uncheck invalid checkboxes
            invalidCheckboxes.forEach((cb) => {
                cb.checked = false;
            });
            updateSummary();
        }
    }

    function updateCheckboxStates() {
        if (!therapistSelect || !therapistSelect.value) {
            // If no therapist selected, disable all checkboxes
            checkboxes.forEach((cb) => {
                cb.disabled = true;
                cb.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.disabled = true;
                selectAllCheckbox.checked = false;
            }
            updateSummary();
            return;
        }

        const selectedTherapistId = parseInt(therapistSelect.value, 10);
        let allMatchingChecked = true;

        checkboxes.forEach((cb) => {
            const sessionTherapistId = parseInt(cb.dataset.therapistId || "0", 10);
            if (sessionTherapistId === selectedTherapistId) {
                cb.disabled = false;
            } else {
                cb.disabled = true;
                cb.checked = false;
                allMatchingChecked = false;
            }
        });

        if (selectAllCheckbox) {
            selectAllCheckbox.disabled = false;
            selectAllCheckbox.checked =
                allMatchingChecked && checkboxes.length > 0;
        }

        updateSummary();
    }

    // Initialize checkbox states based on therapist selection
    if (therapistSelect) {
        therapistSelect.addEventListener("change", function () {
            updateCheckboxStates();
        });
        // Initial state
        updateCheckboxStates();
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function () {
            if (!therapistSelect || !therapistSelect.value) {
                this.checked = false;
                return;
            }

            const selectedTherapistId = parseInt(therapistSelect.value, 10);
            checkboxes.forEach((cb) => {
                const sessionTherapistId = parseInt(
                    cb.dataset.therapistId || "0",
                    10
                );
                if (sessionTherapistId === selectedTherapistId && !cb.disabled) {
                    cb.checked = this.checked;
                }
            });
            updateSummary();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener("click", function () {
            if (!therapistSelect || !therapistSelect.value) {
                errorAlert("Please select a therapist first.");
                return;
            }

            const selectedTherapistId = parseInt(therapistSelect.value, 10);
            checkboxes.forEach((cb) => {
                const sessionTherapistId = parseInt(
                    cb.dataset.therapistId || "0",
                    10
                );
                if (sessionTherapistId === selectedTherapistId && !cb.disabled) {
                    cb.checked = true;
                }
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
            }
            updateSummary();
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener("click", function () {
            checkboxes.forEach((cb) => {
                cb.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            updateSummary();
        });
    }

    checkboxes.forEach((cb) => {
        cb.addEventListener("change", function () {
            validateTherapistSelection();
            updateSummary();
            if (selectAllCheckbox) {
                const selectedTherapistId = therapistSelect
                    ? parseInt(therapistSelect.value, 10)
                    : 0;
                const matchingCheckboxes = Array.from(checkboxes).filter(
                    (c) => {
                        const sessionTherapistId = parseInt(
                            c.dataset.therapistId || "0",
                            10
                        );
                        return (
                            sessionTherapistId === selectedTherapistId && !c.disabled
                        );
                    }
                );
                selectAllCheckbox.checked =
                    matchingCheckboxes.length > 0 &&
                    matchingCheckboxes.every((c) => c.checked);
            }
        });
    });

    form.addEventListener("submit", async function (e) {
        // Validate therapist is selected
        if (!therapistSelect || !therapistSelect.value) {
            e.preventDefault();
            await errorAlert(
                "Please select a therapist before creating a bill."
            );
            return false;
        }

        // Validate at least one session is selected
        const selected = Array.from(checkboxes).filter(
            (cb) => cb.checked && !cb.disabled
        );
        if (selected.length === 0) {
            e.preventDefault();
            await errorAlert(
                "Please select at least one session log to create a bill."
            );
            return false;
        }

        // Validate all selected sessions belong to the selected therapist
        const selectedTherapistId = parseInt(therapistSelect.value, 10);
        const invalidSessions = selected.filter((cb) => {
            const sessionTherapistId = parseInt(cb.dataset.therapistId || "0", 10);
            return sessionTherapistId !== selectedTherapistId;
        });

        if (invalidSessions.length > 0) {
            e.preventDefault();
            await errorAlert(
                "All selected session logs must belong to the selected therapist."
            );
            return false;
        }
    });

    // Initial summary update
    updateSummary();
});

