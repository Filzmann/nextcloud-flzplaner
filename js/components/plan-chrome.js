(function() {
    'use strict';

    /** Zweck: Rendert und bindet Team-, Monats- und Tabnavigation der Assistenzplanung. */
    class PlanChrome {
        constructor(options) {
            this.byId = options.byId;
            this.esc = options.esc;
            this.onTeamChange = options.onTeamChange;
            this.onMonthChange = options.onMonthChange;
            this.onViewChange = options.onViewChange;
            this.teamSelect = this.byId('team-select');
            this.monthInput = this.byId('month-input');
            this.monthPrevious = this.byId('month-prev');
            this.monthNext = this.byId('month-next');
            this.panel = this.byId('adp-panel');
            this.currentMonth = '';
            this.tabs = document.querySelector('.adp-tabs');
            this.tabButtons = Array.from(document.querySelectorAll('.adp-tab'));
            this.teamSelect.addEventListener('change', event => this.onTeamChange(event.target.value));
            this.monthInput.addEventListener('change', event => {
                const value = event.target.value;
                if (!this.isValidMonth(value)) {
                    event.target.value = this.currentMonth;
                    return;
                }
                return this.onMonthChange(value);
            });
            this.monthPrevious.addEventListener('click', () => this.changeMonthBy(-1));
            this.monthNext.addEventListener('click', () => this.changeMonthBy(1));
            this.tabs.addEventListener('click', event => {
                const button = event.target instanceof Element ? event.target.closest('button[data-view]') : null;
                if (button) return this.onViewChange(button.dataset.view || 'month');
            });
            this.tabs.addEventListener('keydown', event => {
                const button = event.target instanceof Element ? event.target.closest('button[data-view]') : null;
                if (!button || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                const current = this.tabButtons.indexOf(button);
                if (current < 0 || this.tabButtons.length === 0) return;
                event.preventDefault();
                const last = this.tabButtons.length - 1;
                const nextIndex = event.key === 'Home' ? 0
                    : event.key === 'End' ? last
                        : event.key === 'ArrowLeft' ? (current + last) % this.tabButtons.length
                            : (current + 1) % this.tabButtons.length;
                const next = this.tabButtons[nextIndex];
                next.focus();
                return this.onViewChange(next.dataset.view || 'month');
            });
        }

        offsetMonth(month, delta) {
            const match = /^(\d{4})-(\d{2})$/.exec(month || '');
            if (!match) return month;
            const value = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1 + delta, 1));
            return `${value.getUTCFullYear()}-${String(value.getUTCMonth() + 1).padStart(2, '0')}`;
        }

        isValidMonth(month) {
            const match = /^(\d{4})-(0[1-9]|1[0-2])$/.exec(month || '');
            if (!match) return false;
            const year = Number(match[1]);
            return year >= 2000 && year <= 2100;
        }

        changeMonthBy(delta) {
            const target = this.offsetMonth(this.monthInput.value, delta);
            if (!this.isValidMonth(target)) return;
            return this.onMonthChange(target);
        }

        render(state) {
            this.teamSelect.innerHTML = state.teams.map(team => {
                const selected = team.code === state.selectedTeamCode ? ' selected' : '';
                return `<option value="${this.esc(team.code)}"${selected}>${this.esc(team.displayName || team.code)}</option>`;
            }).join('');
            this.teamSelect.disabled = state.teams.length === 0;
            this.currentMonth = state.month;
            this.monthInput.value = state.month;
            this.monthPrevious.disabled = state.month === '2000-01';
            this.monthNext.disabled = state.month === '2100-12';
            let activeTabId = '';
            this.tabButtons.forEach(button => {
                const active = button.dataset.view === state.activeView;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.setAttribute('tabindex', active ? '0' : '-1');
                if (active) activeTabId = button.id;
            });
            this.panel.setAttribute('aria-labelledby', activeTabId);
            this.panel.setAttribute('aria-busy', state.loading ? 'true' : 'false');
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.PlanChrome = PlanChrome;
})();
