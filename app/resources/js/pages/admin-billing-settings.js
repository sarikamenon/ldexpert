import $ from "jquery";

$(function () {
    $("#default_generation_day_type").on("change", function () {
        const val = $(this).val();
        $("#dow_wrapper").toggle(val === "day_of_week");
        $("#grace_wrapper").toggle(val === "fixed_delay");
    });
});
