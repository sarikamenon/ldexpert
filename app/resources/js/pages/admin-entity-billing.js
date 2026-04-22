/**
 * Entity Billing Configuration Tab
 * Handles loading, saving, and resetting per-entity billing config.
 */
import $ from "jquery";
import {
    confirmDialog,
    successToast,
    errorAlert,
    showLoading,
    closeAlert,
} from "../common/sweetalert";

document.addEventListener("DOMContentLoaded", () => {
    const $tab = $("#entityBillingTab");
    if (!$tab.length) return;

    const configUrl = $tab.data("config-url");
    const saveUrl = $tab.data("save-url");
    const destroyUrl = $tab.data("destroy-url");
    const entityType = $tab.data("entity-type");
    const entityId = $tab.data("entity-id");
    const csrfToken = $tab.data("csrf");

    const $banner = $("#billingStatusBanner");
    const $bannerIcon = $("#bannerIcon");
    const $bannerTitle = $("#bannerTitle");
    const $bannerSubtitle = $("#bannerSubtitle");
    const $resetBtn = $("#ebResetBtn");
    const $saveBtn = $("#ebSaveBtn");

    // Generation day type toggle
    const $genType = $("#eb_generation_day_type");
    const $dayOfWeekGroup = $("#eb_dayOfWeekGroup");
    const $fixedDelayGroup = $("#eb_fixedDelayGroup");

    function toggleGenerationFields() {
        const value = $genType.val();
        if (value === "fixed_delay") {
            $dayOfWeekGroup.hide();
            $fixedDelayGroup.show();
        } else {
            $dayOfWeekGroup.show();
            $fixedDelayGroup.hide();
        }
    }

    $genType.on("change", toggleGenerationFields);

    // Billing mode card styling
    function updateModeCards() {
        $(".billing-mode-card").each(function () {
            const $card = $(this);
            const isChecked = $card.find("input[type=radio]").is(":checked");
            $card.toggleClass("border-primary bg-primary/5", isChecked);
            $card.toggleClass("border-border", !isChecked);
        });
    }

    $('input[name="billing_mode"]').on("change", updateModeCards);

    // Banner display
    function showBanner(isDefault) {
        $banner.removeClass("hidden");
        if (isDefault) {
            $banner
                .removeClass("border-success bg-success/5")
                .addClass("border-primary/30 bg-primary/5");
            $bannerIcon.html(
                '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
            );
            $bannerTitle.text("Using Global Defaults");
            $bannerSubtitle.text(
                "No custom billing configuration exists. Global defaults are shown below. Save to create a custom configuration.",
            );
            $resetBtn.addClass("hidden");
        } else {
            $banner
                .removeClass("border-primary/30 bg-primary/5")
                .addClass("border-success bg-success/5");
            $bannerIcon.html(
                '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            );
            $bannerTitle.text("Custom Configuration");
            $bannerSubtitle.text(
                "This entity has a custom billing configuration overriding global defaults.",
            );
            $resetBtn.removeClass("hidden");
        }
    }

    // Populate form fields from data
    function populateForm(data) {
        if (data.billing_mode) {
            $(
                `input[name="billing_mode"][value="${data.billing_mode}"]`,
            ).prop("checked", true);
            updateModeCards();
        }

        $("#eb_frequency").val(data.frequency).trigger("change");
        $("#eb_generation_day_type").val(data.generation_day_type).trigger("change");
        toggleGenerationFields();

        if (data.generation_day_of_week !== null) {
            $("#eb_generation_day_of_week").val(data.generation_day_of_week).trigger("change");
        }
        if (data.generation_delay_days !== null) {
            $("#eb_generation_delay_days").val(data.generation_delay_days);
        }

        $("#eb_min_grace_days").val(data.min_grace_days);
        $("#eb_billing_start_date").val(data.billing_start_date || "");
        $("#eb_payment_terms_days").val(data.payment_terms_days);
        $("#eb_auto_generate").prop("checked", !!data.auto_generate);
        $("#eb_auto_send").prop("checked", !!data.auto_send);
        $("#eb_notes").val(data.notes || "");
    }

    // Load configuration
    function loadConfig() {
        $.ajax({
            url: configUrl,
            method: "GET",
            dataType: "json",
            success: function (response) {
                showBanner(response.is_default);
                populateForm(response.data);
            },
            error: function () {
                errorAlert("Failed to load billing configuration.");
            },
        });
    }

    // Save configuration
    $saveBtn.on("click", function () {
        const formData = {
            _token: csrfToken,
            entity_type: entityType,
            entity_id: entityId,
            billing_mode: $('input[name="billing_mode"]:checked').val() || "standard",
            frequency: $("#eb_frequency").val(),
            generation_day_type: $("#eb_generation_day_type").val(),
            generation_day_of_week: $("#eb_generation_day_of_week").val(),
            generation_delay_days: $("#eb_generation_delay_days").val(),
            min_grace_days: $("#eb_min_grace_days").val(),
            billing_start_date: $("#eb_billing_start_date").val() || null,
            payment_terms_days: $("#eb_payment_terms_days").val(),
            auto_generate: $("#eb_auto_generate").is(":checked") ? 1 : 0,
            auto_send: $("#eb_auto_send").is(":checked") ? 1 : 0,
            notes: $("#eb_notes").val(),
        };

        showLoading("Saving configuration...");

        $.ajax({
            url: saveUrl,
            method: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                closeAlert();
                successToast(response.message || "Configuration saved.");
                showBanner(false);
            },
            error: function (xhr) {
                closeAlert();
                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    const messages = Object.values(xhr.responseJSON.errors)
                        .flat()
                        .join("\n");
                    errorAlert(messages, "Validation Error");
                } else {
                    errorAlert(
                        xhr.responseJSON?.message ||
                            "Failed to save configuration.",
                    );
                }
            },
        });
    });

    // Reset to defaults
    $resetBtn.on("click", async function () {
        const result = await confirmDialog({
            title: "Reset to Global Defaults?",
            text: "This will remove the custom billing configuration. The entity will use global defaults instead.",
            icon: "warning",
            confirmButtonText: "Yes, reset",
        });

        if (!result.isConfirmed) return;

        showLoading("Resetting...");

        $.ajax({
            url: destroyUrl,
            method: "DELETE",
            data: { _token: csrfToken },
            dataType: "json",
            success: function (response) {
                closeAlert();
                successToast(
                    response.message || "Reset to global defaults.",
                );
                loadConfig();
            },
            error: function () {
                closeAlert();
                errorAlert("Failed to reset configuration.");
            },
        });
    });

    // Initial load
    loadConfig();
});
