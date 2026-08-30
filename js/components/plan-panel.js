(function() {
    'use strict';

    /** Zweck: Kapselt Paneldarstellung, lokale Schichtlistenaktionen und das Team-Einstellungsformular. */
    class PlanPanel {
        constructor(options) {
            Object.assign(this, options);
            this.panel = this.byId('adp-panel');
            this.workloadOverlay = this.byId('adp-workload-overlay');
            this.panel.addEventListener('click', event => this.handleClick(event));
            this.workloadOverlay?.addEventListener('click', event => this.handleClick(event));
        }

        render(state, team) {
            if (this.workloadOverlay) {
                this.workloadOverlay.hidden = state.activeView !== 'workload';
                this.workloadOverlay.innerHTML = state.activeView === 'workload'
                    ? `<button type="button" class="adp-overlay-close" aria-label="Auslastung schließen" title="Schließen" data-action="close-workload">&times;</button>${this.renderWorkload(state.monthPlan, state.currentUser)}`
                    : '';
            }
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
                this.bindPersonalRegularShiftsForm();
                return;
            }
            if (state.activeView === 'workload') {
                this.panel.innerHTML = this.renderMonth(state.monthPlan, state.currentUser);
                return;
            }
            this.panel.innerHTML = this.renderMonth(state.monthPlan, state.currentUser);
        }

        async handleClick(event) {
            const button = event.target instanceof Element ? event.target.closest('button[data-action]') : null;
            if (!button) return;
            if (button.dataset.action === 'open-candidate-note-editor') return this.setCandidateNoteEditor(button, true);
            if (button.dataset.action === 'close-candidate-note-editor') return this.setCandidateNoteEditor(button, false);
            if (button.dataset.action === 'add-shift-row') return this.addShiftRow();
            if (button.dataset.action === 'remove-shift-row') return this.removeShiftRow(button);
            if (button.dataset.action === 'open-assignment-picker') return this.openAssignmentPicker(button);
            await this.onAction(button);
        }

        setCandidateNoteEditor(button, open) {
            const selector = `[data-candidate-note-entry][data-slot-id="${CSS.escape(button.dataset.slotId || '')}"][data-target-uid="${CSS.escape(button.dataset.targetUid || '')}"]`;
            const editor = this.panel.querySelector(selector)?.querySelector('.adp-shift-note-editor');
            if (!editor) return;
            editor.hidden = !open;
            if (open) editor.querySelector('[data-candidate-note]')?.focus();
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

        bindPersonalRegularShiftsForm() {
            const form = this.byId('personal-regular-shifts-form');
            if (!form) return;
            form.addEventListener('submit', async event => {
                event.preventDefault();
                const data = new FormData(form);
                const rules = data.getAll('regularShift').map(value => {
                    const [weekday, segmentKey] = String(value).split('|');
                    return { weekday: Number(weekday), segmentKey };
                });
                await this.onSavePersonalRegularShifts(rules);
            });
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.PlanPanel = PlanPanel;
})();
