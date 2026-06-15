import $ from "jquery";

$(function () {
    // Standard defaults toggle
    $("#default_generation_day_type").on("change", function () {
        const val = $(this).val();
        $("#dow_wrapper").toggle(val === "day_of_week");
        $("#grace_wrapper").toggle(val === "fixed_delay");
    });

    // Advance defaults toggle
    $("#advance_default_generation_day_type").on("change", function () {
        const val = $(this).val();
        $("#adv_dow_wrapper").toggle(val === "day_of_week");
        $("#adv_grace_wrapper").toggle(val === "fixed_delay");
    });
});
