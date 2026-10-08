(function() {
    const { Model } = window.LocalBase.models;
    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.models = window.FlzPlaner.models || {};

    class DayNote extends Model {
        constructor(data = {}) {
            super();
            this.teamCode = data.teamCode || data.team_code || '';
            this.workDate = data.workDate || data.work_date || '';
            this.note = data.note || '';
            this.updatedByUid = data.updatedByUid || data.updated_by_uid || '';
            this.updatedAt = data.updatedAt || data.updated_at || '';
        }

        toArray() {
            return {
                teamCode: this.teamCode,
                workDate: this.workDate,
                note: this.note,
                updatedByUid: this.updatedByUid,
                updatedAt: this.updatedAt
            };
        }
    }

    window.FlzPlaner.models.DayNote = DayNote;
})();
