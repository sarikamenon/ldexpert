// Convert Lead to Student: show the "New School/Family" panel when no existing school is
// picked, make its body collapsible (collapsed by default), and prefill the fields from the
// lead. Vanilla JS (no jQuery) per project conventions.

document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form[data-lead-last-name]");
    const panel = document.getElementById("new_family_panel");
    const body = document.getElementById("new_family_body");
    const toggle = document.getElementById("new_family_toggle");
    const chevron = document.getElementById("new_family_chevron");
    const schoolSelect = document.getElementById("school_id");

    if (!form || !panel || !body || !toggle || !schoolSelect) {
        return;
    }

    const familyFullName = document.getElementById("family_full_name");
    const familyName = document.getElementById("family_name");
    const familySameAsFull = document.getElementById("family_same_as_full_name");
    const familyState = document.getElementById("family_state");
    const familyTimezone = document.getElementById("family_timezone");
    const familyContactFirst = document.getElementById(
        "family_contact_first_name",
    );
    const familyContactLast = document.getElementById(
        "family_contact_last_name",
    );
    const studentState = document.getElementById("state");
    const studentTimezone = document.getElementById("timezone");

    const leadLastName = form.dataset.leadLastName || "";
    const leadParentName = form.dataset.leadParentName || "";

    const deriveFamilyName = () => {
        if (leadParentName.trim()) {
            return `${leadParentName.trim()} Family`;
        }
        if (leadLastName.trim()) {
            return `${leadLastName.trim()} Family`;
        }
        return "";
    };

    const prefillFamily = () => {
        const derived = deriveFamilyName();
        if (familyFullName && !familyFullName.value) {
            familyFullName.value = derived;
        }
        if (familyName && !familyName.value) {
            familyName.value = derived;
        }
        if (familyState && !familyState.value && studentState) {
            familyState.value = studentState.value;
        }
        if (familyTimezone && !familyTimezone.value && studentTimezone) {
            familyTimezone.value = studentTimezone.value;
        }
        const parts = leadParentName.trim().split(/\s+/);
        if (familyContactFirst && !familyContactFirst.value && parts[0]) {
            familyContactFirst.value = parts[0];
        }
        if (familyContactLast && !familyContactLast.value && parts.length > 1) {
            familyContactLast.value = parts.slice(1).join(" ");
        }
    };

    const setExpanded = (expanded) => {
        body.style.display = expanded ? "" : "none";
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        if (chevron) {
            chevron.style.transform = expanded ? "rotate(180deg)" : "";
        }
        if (expanded) {
            prefillFamily();
        }
    };

    // Show the whole panel only when no existing school is picked.
    const syncPanelVisibility = () => {
        const creating = !schoolSelect.value;
        panel.style.display = creating ? "" : "none";
        if (!creating) {
            setExpanded(false);
        }
    };

    // "Same as Full Name" copies the full name into the NOVA name field.
    if (familyFullName && familyName && familySameAsFull) {
        const syncDisplayName = () => {
            if (familySameAsFull.checked) {
                familyName.value = familyFullName.value;
            }
        };
        familySameAsFull.addEventListener("change", syncDisplayName);
        familyFullName.addEventListener("input", syncDisplayName);
        familyName.addEventListener("input", () => {
            if (familyName.value !== familyFullName.value) {
                familySameAsFull.checked = false;
            }
        });
    }

    toggle.addEventListener("click", () => {
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        setExpanded(!expanded);
    });

    schoolSelect.addEventListener("change", syncPanelVisibility);

    // Initial state: panel expanded by default (aria-expanded="true") when shown.
    syncPanelVisibility();
    setExpanded(toggle.getAttribute("aria-expanded") === "true");
});
