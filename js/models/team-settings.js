(function() {
    const { Model } = window.LocalBase.models;
    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.models = window.FlzPlaner.models || {};

    class TeamSettings extends Model {
        constructor(data = {}) {
            super();
            this.teamCode = data.teamCode || data.team_code || '';
            this.displayName = data.displayName || data.display_name || '';
            this.config = data.config || {};
        }

        toArray() {
            return {
                teamCode: this.teamCode,
                displayName: this.displayName,
                config: this.config
            };
        }
    }

    window.FlzPlaner.models.TeamSettings = TeamSettings;
})();
