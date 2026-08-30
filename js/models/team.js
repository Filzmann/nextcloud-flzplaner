(function() {
    const { Model } = window.LocalBase.models;
    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.models = window.ADPlaner.models || {};
    const { Assistant } = window.ADPlaner.models;

    class Team extends Model {
        constructor(data = {}) {
            super();
            this.code = data.code || '';
            this.groupName = data.groupName || data.group_name || '';
            this.displayName = data.displayName || data.display_name || this.code;
            this.assistants = Assistant.get_all(data.assistants || []);
            this.isEb = !!(data.isEb ?? data.is_eb ?? data.canCoordinate ?? false);
            this.canCoordinate = !!(data.canCoordinate ?? this.isEb);
            this.settings = data.settings || {};
            this.personalWorkload = data.personalWorkload || data.personal_workload || {};
            this.canSetPersonalWorkload = !!(data.canSetPersonalWorkload ?? data.can_set_personal_workload ?? false);
            this.personalRegularShifts = data.personalRegularShifts || data.personal_regular_shifts || [];
            this.canSetRegularShifts = !!(data.canSetRegularShifts ?? data.can_set_regular_shifts ?? false);
        }

        toArray() {
            return {
                code: this.code,
                groupName: this.groupName,
                displayName: this.displayName,
                assistants: this.assistants.map(assistant => assistant.toArray()),
                isEb: this.isEb,
                canCoordinate: this.canCoordinate,
                settings: this.settings,
                personalWorkload: this.personalWorkload,
                canSetPersonalWorkload: this.canSetPersonalWorkload,
                personalRegularShifts: this.personalRegularShifts,
                canSetRegularShifts: this.canSetRegularShifts
            };
        }
    }

    window.ADPlaner.models.Team = Team;
})();
