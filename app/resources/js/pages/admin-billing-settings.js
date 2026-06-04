import $ from "jquery";

// The generation-type fields render through the Select2-based x-ui::select
// component, which dispatches its change via jQuery .trigger('change') — a native
// addEventListener('change') would never fire. Bind through jQuery to match.
function wireGenerationToggle(selectId, dowWrapperId, delayWrapperId) {
    const $select = $("#" + selectId);
    if (!$select.length) {
        return;
    }

    $select.on("change", function () {
        const isDayOfWeek = $(this).val() === "day_of_week";
        $("#" + dowWrapperId).toggle(isDayOfWeek);
        $("#" + delayWrapperId).toggle(!isDayOfWeek);
    });
}

$(function () {
    wireGenerationToggle("default_generation_day_type", "dow_wrapper", "delay_wrapper");
    wireGenerationToggle("advance_default_generation_day_type", "adv_dow_wrapper", "adv_delay_wrapper");
    wireGenerationToggle("standard_default_generation_day_type", "std_dow_wrapper", "std_delay_wrapper");
});
