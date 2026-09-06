(function() {
    'use strict';

    /**
     * Zweck: Orchestriert Ladezustand und fachliche UI-Workflows der Assistenzplanung.
     * Zusammenspiel: main.js -> PlanApp -> PlanChrome/PlanPanel/PlanRepository.
     */
    class PlanApp {
        constructor(options) {
            this.repository = options.repository;
            this.showNotice = options.showNotice;
            this.showError = options.showError;
            this.loadVersion = 0;
            this.settingsSaving = false;
            this.state = { currentUser: null, teams: [], selectedTeamCode: '', month: '', activeView: 'month', monthPlan: null, organization: {}, loading: false };
            this.chrome = new options.PlanChrome({
                byId: options.byId, esc: options.esc,
                onTeamChange: value => this.selectTeam(value),
                onMonthChange: value => this.selectMonth(value),
                onViewChange: value => this.selectView(value),
            });
            this.panel = new options.PlanPanel({
                byId: options.byId,
                renderMonth: options.renderMonth,
                renderWorkload: options.renderWorkload,
                renderSettings: options.renderSettings,
                addShiftRow: options.addShiftRow,
                removeShiftRow: options.removeShiftRow,
                collectShifts: options.collectShifts,
                openAssignmentPicker: options.openAssignmentPicker,
                onAction: button => this.handleAction(button),
                onSaveSettings: values => this.saveSettings(values),
                onSavePersonalWorkload: values => this.savePersonalWorkload(values),
                onSavePersonalRegularShifts: rules => this.savePersonalRegularShifts(rules),
            });
        }

        selectedTeam() { return this.state.teams.find(team => team.code === this.state.selectedTeamCode) || null; }
        render() { this.chrome.render(this.state); this.panel.render(this.state, this.selectedTeam()); }

        async init() {
            try {
                const data = await this.repository.state();
                this.applyState(data, true);
                this.render();
                if (this.state.selectedTeamCode) await this.reloadActive();
                else {
                    this.showNotice(`Keine Assistenzteam-Gruppe mit dem konfigurierten Präfix ${this.state.organization.teamGroupPrefix || ''} gefunden.`);
                    this.render();
                }
            } catch (error) { this.showError(error, 'Dienstplanung konnte nicht geladen werden.'); }
        }

        applyState(data, initial = false) {
            this.state.currentUser = data.currentUser || this.state.currentUser;
            this.state.teams = data.teams || [];
            this.state.organization = data.organization || this.state.organization;
            if (initial) this.state.month = data.defaultMonth || new Date().toISOString().slice(0, 7);
            if (!this.state.teams.some(team => team.code === this.state.selectedTeamCode)) this.state.selectedTeamCode = this.state.teams[0]?.code || '';
        }

        async reloadActive() {
            const loadVersion = ++this.loadVersion;
            this.state.loading = true; this.render(); this.showNotice('');
            try {
                if (this.state.activeView === 'settings') {
                    const data = await this.repository.state();
                    if (loadVersion === this.loadVersion) this.applyState(data);
                } else {
                    await this.loadMonth(loadVersion);
                }
            } catch (error) {
                if (loadVersion === this.loadVersion) this.showError(error, 'Ansicht konnte nicht geladen werden.');
            } finally {
                if (loadVersion === this.loadVersion) {
                    this.state.loading = false;
                    this.render();
                }
            }
        }

        async loadMonth(loadVersion = ++this.loadVersion) {
            const teamCode = this.state.selectedTeamCode;
            const month = this.state.month;
            const monthPlan = await this.repository.monthPlan(teamCode, month);
            if (loadVersion !== this.loadVersion || teamCode !== this.state.selectedTeamCode || month !== this.state.month || !['month', 'workload'].includes(this.state.activeView)) {
                return false;
            }
            this.state.monthPlan = monthPlan;
            const updatedTeam = monthPlan.team;
            const existingTeam = this.selectedTeam();
            updatedTeam.personalWorkload = existingTeam?.personalWorkload || {};
            updatedTeam.canSetPersonalWorkload = !!existingTeam?.canSetPersonalWorkload;
            updatedTeam.personalRegularShifts = existingTeam?.personalRegularShifts || [];
            updatedTeam.canSetRegularShifts = !!existingTeam?.canSetRegularShifts;
            this.state.teams = this.state.teams.map(team => team.code === updatedTeam.code ? updatedTeam : team);
            return true;
        }

        async selectTeam(value) {
            this.state.selectedTeamCode = value;
            if (this.state.activeView === 'workload' && !this.selectedTeam()?.canCoordinate) this.state.activeView = 'month';
            this.state.monthPlan = null;
            await this.reloadActive();
        }
        async selectMonth(value) {
            this.state.month = value;
            this.state.monthPlan = null;
            if (this.state.activeView !== 'settings') await this.reloadActive();
        }
        async selectView(value) {
            this.state.activeView = value === 'workload' && !this.selectedTeam()?.canCoordinate ? 'month' : value;
            this.render();
            await this.reloadActive();
        }

        async handleAction(button) {
            if (button.disabled) return;
            button.disabled = true;
            let mutationCompleted = false;
            try {
                const action = button.dataset.action;
                const team = this.state.selectedTeamCode; const month = this.state.month; const slot = button.dataset.slotId;
                if (action === 'close-workload') { await this.selectView('month'); return; }
                if (action === 'add-self') await this.repository.addSelf(team, month, slot);
                else if (action === 'add-selected') {
                    const targetUid = button.dataset.targetUid || '';
                    if (!targetUid) return;
                    await this.repository.addSelected(team, month, slot, targetUid);
                } else if (action === 'remove-candidate') {
                    const fixed=button.closest('[data-candidate-chip]')?.dataset.fixed==='true';
                    if (fixed && !window.confirm('Diese feste Schicht wirklich entfernen? Sie wird für dieses Datum dauerhaft als nicht möglich gespeichert.')) return;
                    await this.repository.removeCandidate(team, month, slot, button.dataset.targetUid || '');
                }
                else if (action === 'transition-status') await this.repository.transitionStatus(team, month, button.dataset.targetStatus || '');
                else if (action === 'report-fixed-conflict') await this.repository.reportFixedConflict(team,month,slot);
                else if (action === 'resolve-fixed-conflict') await this.repository.resolveFixedConflict(team,month,slot,button.dataset.keptUid || '');
                else if (action === 'set-candidate-preference' || action === 'save-candidate-note' || action === 'delete-candidate-note') {
                    const chip = button.closest('[data-candidate-chip]');
                    const entrySelector = `[data-candidate-note-entry][data-slot-id="${CSS.escape(slot || '')}"][data-target-uid="${CSS.escape(button.dataset.targetUid || '')}"]`;
                    const entry = button.closest('[data-candidate-note-entry]') || this.panel.panel.querySelector(entrySelector);
                    const note = action === 'delete-candidate-note' ? '' : entry?.querySelector('[data-candidate-note]')?.value || '';
                    const preference = action === 'set-candidate-preference' ? button.dataset.preference || 'neutral' : entry?.dataset.currentPreference || chip?.dataset.currentPreference || 'neutral';
                    await this.repository.updateCandidateMetadata(team, month, slot, preference, note);
                }
                else if (action === 'save-note') {
                    const noteContainer = button.closest('.adp-note-cell, .adp-mobile-notes');
                    const textarea = noteContainer?.querySelector(`textarea[data-note-date="${CSS.escape(button.dataset.date)}"]`);
                    await this.repository.saveDayNote(team, month, button.dataset.date, textarea ? textarea.value : '');
                } else return;
                mutationCompleted = true;
                this.state.monthPlan = null;
                await this.loadMonth();
            } catch (error) {
                this.showError(
                    error,
                    mutationCompleted
                        ? 'Die Änderung wurde gespeichert, aber der Monatsplan konnte nicht neu geladen werden.'
                        : 'Aktion konnte nicht ausgeführt werden.'
                );
            }
            finally {
                button.disabled = false;
                this.panel.render(this.state, this.selectedTeam());
            }
        }

        async saveSettings(values) {
            if (this.settingsSaving) return;
            this.settingsSaving = true;
            let mutationCompleted = false;
            try {
                if (values.shifts.length === 0) throw new Error('Mindestens eine Schicht muss konfiguriert sein.');
                await this.repository.saveSettings(this.state.selectedTeamCode, values.displayName, values.meetingDay, values.shifts);
                mutationCompleted = true;
                this.state.monthPlan = null;
                this.applyState(await this.repository.state());
                this.state.activeView = 'month';
                await this.loadMonth();
            } catch (error) {
                this.showError(
                    error,
                    mutationCompleted
                        ? 'Die Einstellungen wurden gespeichert, aber die Ansicht konnte nicht neu geladen werden.'
                        : 'Einstellungen konnten nicht gespeichert werden.'
                );
            } finally {
                this.settingsSaving = false;
                if (mutationCompleted) this.render();
            }
        }

        async savePersonalWorkload(values) {
            if (this.settingsSaving) return;
            this.settingsSaving = true;
            let mutationCompleted = false;
            try {
                await this.repository.savePersonalWorkload(this.state.selectedTeamCode, values);
                mutationCompleted = true;
                this.applyState(await this.repository.state());
            } catch (error) {
                this.showError(error, mutationCompleted ? 'Grenzen wurden gespeichert, aber die Ansicht konnte nicht neu geladen werden.' : 'Persönliche Grenzen konnten nicht gespeichert werden.');
            } finally {
                this.settingsSaving = false;
                this.render();
            }
        }

        async savePersonalRegularShifts(rules) {
            if (this.settingsSaving) return;
            this.settingsSaving = true;
            try {
                await this.repository.savePersonalRegularShifts(this.state.selectedTeamCode,rules);
                this.applyState(await this.repository.state());
            } catch (error) { this.showError(error,'Regelmäßige Schichten konnten nicht gespeichert werden.'); }
            finally { this.settingsSaving=false; this.render(); }
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.PlanApp = PlanApp;
})();
