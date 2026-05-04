/**
 * NOVA Brand Colors for Charts
 * Centralized color configuration for Chart.js visualizations
 */

export const novaChartColors = {
    // Primary brand colors
    primary: '#5563b8',      // Blue-purple (primary)
    primaryLight: '#6a7bc9', // Blue-purple-400
    primaryDark: '#4a56a0',  // Blue-purple-600
    
    // Secondary brand colors
    secondary: '#14b8a6',    // Teal (secondary)
    secondaryLight: '#2dd4bf', // Teal-400
    secondaryDark: '#0d9488',  // Teal-600
    
    // Accent brand colors
    accent: '#a855f7',       // Purple-500
    accentLight: '#c084fc',  // Purple-400
    accentDark: '#9333ea',   // Purple-600
    
    // Gradient colors
    cyan: '#06b6d4',         // Cyan-500
    fuchsia: '#d946ef',      // Fuchsia-500
    
    // Chart palette (using NOVA brand colors)
    palette: [
        '#5563b8',  // Blue-purple (primary)
        '#14b8a6',  // Teal (secondary)
        '#a855f7',  // Purple (accent)
        '#06b6d4',  // Cyan
        '#d946ef',  // Fuchsia
        '#2dd4bf',  // Teal light
        '#c084fc',  // Purple light
        '#9333ea',  // Purple dark
    ],
    
    // Extended palette for multiple datasets
    extendedPalette: [
        '#5563b8',  // Blue-purple (primary)
        '#14b8a6',  // Teal (secondary)
        '#a855f7',  // Purple
        '#06b6d4',  // Cyan
        '#d946ef',  // Fuchsia
        '#2dd4bf',  // Teal-400
        '#c084fc',  // Purple-400
        '#6a7bc9',  // Blue-purple-400
        '#0d9488',  // Teal-600
        '#9333ea',  // Purple-600
    ],
    
    // Background colors with opacity
    primaryBg: 'rgba(85, 99, 184, 0.1)',
    accentBg: 'rgba(168, 85, 247, 0.1)',
    cyanBg: 'rgba(6, 182, 212, 0.1)',
    fuchsiaBg: 'rgba(217, 70, 239, 0.1)',
    
    // Success/Warning/Danger (keeping for compatibility)
    success: '#22c55e',
    warning: '#f59e0b',
    danger: '#ef4444',
    successBg: 'rgba(34, 197, 94, 0.1)',
    warningBg: 'rgba(245, 158, 11, 0.1)',
    dangerBg: 'rgba(239, 68, 68, 0.1)',

    // Neutral slate tones for non-billable / inactive series
    muted: '#94a3b8',       // Slate-400
    mutedLight: '#cbd5e1',  // Slate-300
};

/**
 * Resolve a semantic chart color key (e.g. from a PHP enum's chartColorKey())
 * to a concrete hex. Falls back to primary if the key is unknown.
 */
export function resolveChartColor(key) {
    return novaChartColors[key] ?? novaChartColors.primary;
}

/**
 * Get chart colors for a specific number of datasets
 */
export function getChartColors(count = 5) {
    return novaChartColors.extendedPalette.slice(0, count);
}

/**
 * Get background colors with opacity for charts
 */
export function getChartBackgroundColors(count = 5) {
    return [
        novaChartColors.primaryBg,
        novaChartColors.accentBg,
        novaChartColors.cyanBg,
        novaChartColors.fuchsiaBg,
        novaChartColors.primaryBg,
    ].slice(0, count);
}

