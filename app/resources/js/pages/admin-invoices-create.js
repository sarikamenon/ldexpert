/**
 * Invoice Create Page JavaScript
 * Handles session log selection, select all/deselect all, summary calculation,
 * and validation to ensure all selected sessions belong to the selected school
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
    const form = document.getElementById("createInvoiceForm");
    const schoolSelect = document.getElementById("school_id");

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

    function validateSchoolSelection() {
        if (!schoolSelect || !schoolSelect.value) {
            return;
        }

        const selectedSchoolId = parseInt(schoolSelect.value, 10);
        const invalidCheckboxes = Array.from(checkboxes).filter((cb) => {
            if (!cb.checked) {
                return false;
            }
            const sessionSchoolId = parseInt(cb.dataset.schoolId || "0", 10);
            return sessionSchoolId !== selectedSchoolId;
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
        if (!schoolSelect || !schoolSelect.value) {
            // If no school selected, disable all checkboxes
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

        const selectedSchoolId = parseInt(schoolSelect.value, 10);
        let allMatchingChecked = true;

        checkboxes.forEach((cb) => {
            const sessionSchoolId = parseInt(cb.dataset.schoolId || "0", 10);
            if (sessionSchoolId === selectedSchoolId) {
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

    // Initialize checkbox states based on school selection
    if (schoolSelect) {
        schoolSelect.addEventListener("change", function () {
            updateCheckboxStates();
        });
        // Initial state
        updateCheckboxStates();
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function () {
            if (!schoolSelect || !schoolSelect.value) {
                this.checked = false;
                return;
            }

            const selectedSchoolId = parseInt(schoolSelect.value, 10);
            checkboxes.forEach((cb) => {
                const sessionSchoolId = parseInt(
                    cb.dataset.schoolId || "0",
                    10
                );
                if (sessionSchoolId === selectedSchoolId && !cb.disabled) {
                    cb.checked = this.checked;
                }
            });
            updateSummary();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener("click", function () {
            if (!schoolSelect || !schoolSelect.value) {
                errorAlert("Please select a school first.");
                return;
            }

            const selectedSchoolId = parseInt(schoolSelect.value, 10);
            checkboxes.forEach((cb) => {
                const sessionSchoolId = parseInt(
                    cb.dataset.schoolId || "0",
                    10
                );
                if (sessionSchoolId === selectedSchoolId && !cb.disabled) {
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
            validateSchoolSelection();
            updateSummary();
            if (selectAllCheckbox) {
                const selectedSchoolId = schoolSelect
                    ? parseInt(schoolSelect.value, 10)
                    : 0;
                const matchingCheckboxes = Array.from(checkboxes).filter(
                    (c) => {
                        const sessionSchoolId = parseInt(
                            c.dataset.schoolId || "0",
                            10
                        );
                        return (
                            sessionSchoolId === selectedSchoolId && !c.disabled
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
        // Validate school is selected
        if (!schoolSelect || !schoolSelect.value) {
            e.preventDefault();
            await errorAlert(
                "Please select a school before creating an invoice."
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
                "Please select at least one session log to create an invoice."
            );
            return false;
        }

        // Validate all selected sessions belong to the selected school
        const selectedSchoolId = parseInt(schoolSelect.value, 10);
        const invalidSessions = selected.filter((cb) => {
            const sessionSchoolId = parseInt(cb.dataset.schoolId || "0", 10);
            return sessionSchoolId !== selectedSchoolId;
        });

        if (invalidSessions.length > 0) {
            e.preventDefault();
            await errorAlert(
                "All selected session logs must belong to the selected school."
            );
            return false;
        }
    });

    // Initial summary update
    updateSummary();
});
