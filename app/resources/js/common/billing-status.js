/**
 * Single source of truth for billing status display metadata.
 *
 * To add or remove a billing status, edit only this file.
 * All badge rendering, label lookups, and SweetAlert select options
 * are derived from this map automatically.
 */
export const BILLING_STATUSES = {
    pending: {
        label: 'Pending',
        badgeCls: 'bg-warning/10 text-warning',
    },
    billed: {
        label: 'Billed',
        badgeCls: 'bg-success/10 text-success',
    },
    not_billable: {
        label: 'Not Billable',
        badgeCls: 'bg-foreground/10 text-foreground/70',
    },
};

/**
 * Returns the human-readable label for a billing status value.
 *
 * @param {string} value
 * @returns {string}
 */
export function getBillingLabel(value) {
    return BILLING_STATUSES[value]?.label ?? (value ? value.replace(/_/g, ' ') : '-');
}

/**
 * Returns an HTML badge <span> for a billing status value.
 *
 * @param {string} value
 * @returns {string}
 */
export function getBillingBadge(value) {
    const s = BILLING_STATUSES[value] ?? { label: value || '-', badgeCls: 'bg-foreground/10 text-foreground/70' };
    return `<span class="text-xs font-medium px-2 py-0.5 rounded-full ${s.badgeCls}">${s.label}</span>`;
}

/**
 * Returns an object suitable for SweetAlert2 `inputOptions` (input: 'select'),
 * containing only the statuses a therapist can transition to (i.e. not 'pending').
 *
 * @returns {Record<string, string>}
 */
export const BILLING_STATUS_TRANSITION_OPTIONS = Object.fromEntries(
    Object.entries(BILLING_STATUSES)
        .filter(([key]) => key !== 'pending')
        .map(([key, { label }]) => [key, label])
);
