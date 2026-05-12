document.addEventListener('DOMContentLoaded', () => {
    initGoalsFilter();
    initSsaToggles();
    initProgressNotesToggle();
});

function initGoalsFilter() {
    const pills = document.querySelectorAll('.goals-filter-pill');
    if (!pills.length) return;

    pills.forEach((pill) => {
        pill.addEventListener('click', () => {
            const filter = pill.dataset.filter;

            pills.forEach((p) => {
                const isActive = p === pill;
                p.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                if (isActive) {
                    p.classList.add('border-foreground', 'ring-1', 'ring-foreground', 'text-foreground');
                    p.classList.remove('border-border', 'text-foreground/70');
                } else {
                    p.classList.remove('border-foreground', 'ring-1', 'ring-foreground', 'text-foreground');
                    p.classList.add('border-border', 'text-foreground/70');
                }
            });

            applyGoalFilter(filter);
        });
    });
}

function applyGoalFilter(filter) {
    const sections = document.querySelectorAll('.ssa-goal-section');

    sections.forEach((section) => {
        const items = section.querySelectorAll('.goal-item');
        let visibleCount = 0;

        items.forEach((item) => {
            const status = item.dataset.status;
            const visible = filter === 'all' || status === filter;
            item.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        // Hide section if no goals match
        section.style.display = visibleCount > 0 ? '' : 'none';

        // Expand body if it was collapsed and now has visible items
        if (visibleCount > 0) {
            const body = section.querySelector('.ssa-goals-body');
            const toggle = section.querySelector('.ssa-toggle');
            if (body && toggle && toggle.getAttribute('aria-expanded') === 'false') {
                expandSsaSection(toggle, body);
            }
        }
    });
}

function initSsaToggles() {
    document.querySelectorAll('.ssa-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const bodyId = btn.getAttribute('aria-controls');
            const body = document.getElementById(bodyId);
            if (!body) return;

            const expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                collapseSsaSection(btn, body);
            } else {
                expandSsaSection(btn, body);
            }
        });
    });
}

function expandSsaSection(btn, body) {
    body.style.display = '';
    btn.setAttribute('aria-expanded', 'true');
    const chevron = btn.querySelector('.ssa-chevron');
    if (chevron) chevron.classList.add('rotate-180');
}

function collapseSsaSection(btn, body) {
    body.style.display = 'none';
    btn.setAttribute('aria-expanded', 'false');
    const chevron = btn.querySelector('.ssa-chevron');
    if (chevron) chevron.classList.remove('rotate-180');
}

function initProgressNotesToggle() {
    document.querySelectorAll('.progress-notes-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const panelId = btn.getAttribute('aria-controls');
            const panel = document.getElementById(panelId);
            if (!panel) return;

            const expanded = btn.getAttribute('aria-expanded') === 'true';
            const chevron = btn.querySelector('.progress-chevron');
            const label = btn.querySelector('.toggle-label');

            if (expanded) {
                panel.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.classList.remove('rotate-90');
                if (label) label.textContent = 'Show progress notes';
            } else {
                panel.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.classList.add('rotate-90');
                if (label) label.textContent = 'Hide progress notes';
            }
        });
    });
}
