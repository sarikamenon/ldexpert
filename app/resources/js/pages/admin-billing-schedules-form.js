/**
 * Billing Schedule Create/Edit Form — toggles day-of-week vs fixed-delay fields
 * and auto-sets schedulable_type based on schedule_type selection.
 */
document.addEventListener("DOMContentLoaded", () => {
    const genTypeSelect = document.getElementById("generation_day_type");
    const dayOfWeekGroup = document.getElementById("dayOfWeekGroup");
    const fixedDelayGroup = document.getElementById("fixedDelayGroup");
    const scheduleTypeSelect = document.getElementById("schedule_type");
    const schedulableTypeInput = document.getElementById("schedulable_type");

    function toggleGenerationFields() {
        if (!genTypeSelect || !dayOfWeekGroup || !fixedDelayGroup) return;

        const value = genTypeSelect.value;
        if (value === "fixed_delay") {
            dayOfWeekGroup.style.display = "none";
            fixedDelayGroup.style.display = "";
        } else {
            dayOfWeekGroup.style.display = "";
            fixedDelayGroup.style.display = "none";
        }
    }

    function updateSchedulableType() {
        if (!scheduleTypeSelect || !schedulableTypeInput) return;

        const type = scheduleTypeSelect.value;
        if (type === "therapist_bill") {
            schedulableTypeInput.value = "App\\Models\\User";
        } else {
            schedulableTypeInput.value = "App\\Models\\School";
        }
    }

    if (genTypeSelect) {
        genTypeSelect.addEventListener("change", toggleGenerationFields);
        toggleGenerationFields();
    }

    if (scheduleTypeSelect) {
        scheduleTypeSelect.addEventListener("change", () => {
            updateSchedulableType();
        });
    }
});
