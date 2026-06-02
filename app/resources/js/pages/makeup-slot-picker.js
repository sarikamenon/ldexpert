// Public make-up slot picker: warns the parent when the times they pick for two
// different missed sessions overlap each other, before the form is submitted.
// Server-side validation (MakeupBookingService) remains the authoritative gate;
// this is only a UX nicety.

function getSelectedSlotInfo(select) {
    if (!select.value) return null;
    const option = select.options[select.selectedIndex];
    return {
        startUtc: select.value,
        endUtc: option.dataset.end,
        selectElement: select,
    };
}

function intervalsOverlap(start1, end1, start2, end2) {
    const s1 = new Date(start1).getTime();
    const e1 = new Date(end1).getTime();
    const s2 = new Date(start2).getTime();
    const e2 = new Date(end2).getTime();
    return s1 < e2 && s2 < e1;
}

function checkForConflicts(selects) {
    const selectedSlots = [];

    for (const select of selects) {
        const slot = getSelectedSlotInfo(select);
        if (slot) {
            selectedSlots.push(slot);
        }
    }

    for (let i = 0; i < selectedSlots.length; i++) {
        for (let j = i + 1; j < selectedSlots.length; j++) {
            const slot1 = selectedSlots[i];
            const slot2 = selectedSlots[j];

            if (intervalsOverlap(slot1.startUtc, slot1.endUtc, slot2.startUtc, slot2.endUtc)) {
                return {
                    hasConflict: true,
                    reason: 'Selected time slots overlap. Please choose non-overlapping times for each session.',
                };
            }
        }
    }

    return { hasConflict: false, reason: '' };
}

function initSlotPicker() {
    const form = document.getElementById('slot-form');
    if (!form) return;

    const selects = form.querySelectorAll('select.slot-select');
    const conflictBanner = document.getElementById('conflict-banner');

    function setConflict(message) {
        if (conflictBanner) conflictBanner.textContent = message;
    }

    form.addEventListener('submit', function (e) {
        const conflict = checkForConflicts(selects);
        if (conflict.hasConflict) {
            e.preventDefault();
            setConflict(conflict.reason);
            window.scrollTo(0, 0);
        }
    });

    for (const select of selects) {
        select.addEventListener('change', function () {
            const conflict = checkForConflicts(selects);
            setConflict(conflict.hasConflict ? conflict.reason : '');
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlotPicker);
} else {
    initSlotPicker();
}
