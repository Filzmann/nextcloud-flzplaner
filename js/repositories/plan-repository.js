(function() {
    const { Repository } = window.LocalBase.repositories;
    const { ShiftDefinition, ShiftSlot, Team } = window.ADPlaner.models;

    class PlanRepository extends Repository {
        async state() {
            const data = await this.request('/api/state');

            return {
                ...data,
                teams: Team.get_all(data.teams || [])
            };
        }

        async monthPlan(teamCode, month) {
            return this.hydrateMonthPlan(await this.request(this.teamPath(teamCode) + '/months/' + this.encode(month)));
        }

        addSelf(teamCode, month, slotId) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/candidates');
        }

        addSelected(teamCode, month, slotId, targetUid) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/candidates', {
                targetUid
            });
        }

        removeCandidate(teamCode, month, slotId, targetUid) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/candidates/remove', {
                targetUid
            });
        }

        updateCandidateMetadata(teamCode, month, slotId, preference, note) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/candidate-metadata', { preference, note });
        }

        savePersonalWorkload(teamCode, limits) {
            return this.post(this.teamPath(teamCode) + '/personal-workload', limits);
        }

        savePersonalRegularShifts(teamCode, rules) {
            return this.post(this.teamPath(teamCode) + '/personal-regular-shifts', { regularShiftsJson: JSON.stringify(rules) });
        }

        reportFixedConflict(teamCode, month, slotId) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/fixed-conflict/report');
        }

        resolveFixedConflict(teamCode, month, slotId, keptUid) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/slots/' + this.encode(slotId) + '/fixed-conflict/resolve', { keptUid });
        }

        saveDayNote(teamCode, month, date, note) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/days/' + this.encode(date) + '/note', {
                note
            });
        }

        transitionStatus(teamCode, month, targetStatus) {
            return this.post(this.teamPath(teamCode) + '/months/' + this.encode(month) + '/status', {
                targetStatus
            });
        }

        saveSettings(teamCode, displayName, meetingDay, shifts) {
            return this.post(this.teamPath(teamCode) + '/settings', {
                displayName,
                meetingDay,
                shiftsJson: JSON.stringify(shifts)
            });
        }

        teamPath(teamCode) {
            return '/api/teams/' + this.encode(teamCode);
        }

        hydrateMonthPlan(plan) {
            if (!plan) {
                return null;
            }

            return {
                ...plan,
                team: Team.get(plan.team),
                segments: ShiftDefinition.get_all(plan.segments || []),
                days: (plan.days || []).map(day => ({
                    ...day,
                    slots: ShiftSlot.get_all(day.slots || [])
                }))
            };
        }

    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.repositories = window.ADPlaner.repositories || {};
    window.ADPlaner.repositories.PlanRepository = PlanRepository;
})();
