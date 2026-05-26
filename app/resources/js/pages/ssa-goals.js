import { initSsaGoalStatusButtons } from '../common/ssa-goal-status';

document.addEventListener('DOMContentLoaded', () => {
    initSsaGoalStatusButtons();
    initGoalsListToggles();
});

function initGoalsListToggles() {
    initCollapsibleButtons('.gl-objectives-toggle', '.gl-obj-chevron');
    initCollapsibleButtons('.gl-progress-toggle', '.gl-prog-chevron');
}

function initCollapsibleButtons(btnSelector, chevronSelector) {
    document.querySelectorAll(btnSelector).forEach((btn) => {
        btn.addEventListener('click', () => {
            const panelId = btn.getAttribute('aria-controls');
            const panel = document.getElementById(panelId);
            if (!panel) return;

            const expanded = btn.getAttribute('aria-expanded') === 'true';
            const chevron = btn.querySelector(chevronSelector);

            if (expanded) {
                panel.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.classList.remove('rotate-90');
            } else {
                panel.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.classList.add('rotate-90');
            }
        });
    });
}
