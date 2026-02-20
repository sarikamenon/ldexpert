/**
 * Admin Session Log Override – rate ↔ amount instant sync
 * When rate type or rate amount changes, recalculates billable/invoice amount.
 * When amount changes, reverse-calculates rate.
 * Uses same formulas as backend: hourly = rate * (duration_minutes/60), flat = rate.
 */

import $ from 'jquery';

function round2(value) {
    return Math.round(value * 100) / 100;
}

function amountFromRate(rateType, rateAmount, durationMinutes) {
    if (rateType === 'H') {
        return round2(rateAmount * (durationMinutes / 60));
    }
    return round2(rateAmount);
}

function rateFromAmount(rateType, amount, durationMinutes) {
    if (rateType === 'H' && durationMinutes > 0) {
        return round2(amount / (durationMinutes / 60));
    }
    return round2(amount);
}

function bindRateSync(prefix, durationMinutes) {
    if (durationMinutes <= 0) {
        return;
    }

    const typeSel = `#${prefix}_rate_type`;
    const rateInput = `#${prefix}_rate_amount`;
    const amountInput = prefix === 'therapist' ? '#therapist_billable_amount' : '#school_invoice_amount';

    const $type = $(typeSel);
    const $rate = $(rateInput);
    const $amount = $(amountInput);

    if (!$type.length || !$rate.length || !$amount.length) {
        return;
    }

    function syncRateToAmount() {
        const rateType = $type.val();
        const rateVal = parseFloat($rate.val(), 10);
        if (rateType !== 'H' && rateType !== 'F') {
            return;
        }
        if (Number.isNaN(rateVal) || rateVal < 0) {
            return;
        }
        const amount = amountFromRate(rateType, rateVal, durationMinutes);
        $amount.val(amount.toFixed(2));
    }

    function syncAmountToRate() {
        const rateType = $type.val();
        const amountVal = parseFloat($amount.val(), 10);
        if (rateType !== 'H' && rateType !== 'F') {
            return;
        }
        if (Number.isNaN(amountVal) || amountVal < 0) {
            return;
        }
        const rate = rateFromAmount(rateType, amountVal, durationMinutes);
        $rate.val(rate.toFixed(2));
    }

    $type.on('change', syncRateToAmount);
    $rate.on('input change', syncRateToAmount);
    $amount.on('input change', syncAmountToRate);
}

$(function () {
    const dataEl = document.getElementById('admin-session-log-override-form-container');
    const durationMinutes = dataEl
        ? parseInt(dataEl.getAttribute('data-duration-minutes') || '0', 10)
        : 0;

    if (durationMinutes <= 0) {
        return;
    }

    bindRateSync('therapist', durationMinutes);
    bindRateSync('school', durationMinutes);
});
