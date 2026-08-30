(function() {
    'use strict';

    /** Zweck: Kapselt Paneldarstellung, lokale Schichtlistenaktionen und das Team-Einstellungsformular. */
    class PlanPanel {
        constructor(options) {
            Object.assign(this, options);
            this.panel = this.byId('adp-panel');
            this.panel.addEventListener('click', event => this.handleClick(event));
        }

        render(state, team) {
            if (state.loading) {
                this.panel.innerHTML = '<p class="adp-loading">Lade...</p>';
                return;
            }
            if (!state.selectedTeamCode) {
                this.panel.innerHTML = '<p>Keine Assistenznehmer-Gruppen.</p>';
                return;
            }
            if (state.activeView === 'settings') {
                this.panel.innerHTML = this.renderSettings(team);
                this.bindSettingsForm();
                this.bindPersonalWorkloadForm();
                return;
            }
            this.panel.innerHTML = this.renderMonth(state.monthPlan, state.currentUser);
        }

        async handleClick(event) {
            const button = event.target instanceof Element ? event.target.closest('button[data-action]') : null;
            if (!button) return;
            if (button.dataset.action === 'add-shift-row') return this.addShiftRow();
            if (button.dataset.action === 'remove-shift-row') return this.removeShiftRow(button);
            if (button.dataset.action === 'open-assignment-picker') return this.openAssignmentPicker(button);
            await this.onAction(button);
        }

        bindSettingsForm() {
            const form = this.byId('settings-form');
            if (!form) return;
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const data = new FormData(form);
                const shifts = this.collectShifts(form);
                await this.onSaveSettings({ displayName: data.get('displayName') || '', meetingDay: data.get('meetingDay') || '', shifts });
            });
        }

        bindPersonalWorkloadForm() {
            const form = this.byId('personal-workload-form');
            if (!form) return;
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const data = new FormData(form);
                await this.onSavePersonalWorkload({
                    weeklyMin: data.get('weeklyMin') || '',
                    weeklyMax: data.get('weeklyMax') || '',
                    monthlyMin: data.get('monthlyMin') || '',
                    monthlyMax: data.get('monthlyMax') || '',
                });
            });
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.PlanPanel = PlanPanel;
})();
