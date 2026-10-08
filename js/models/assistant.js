(function() {
    const { Model } = window.LocalBase.models;
    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.models = window.FlzPlaner.models || {};

    class Assistant extends Model {
        constructor(data = {}) {
            super();
            this.uid = data.uid || '';
            this.displayName = data.displayName || data.display_name || this.uid;
            this.isEb = !!(data.isEb ?? data.is_eb ?? false);
            this.canReceiveShifts = data.canReceiveShifts ?? data.can_receive_shifts ?? true;
        }

        toArray() {
            return {
                uid: this.uid,
                displayName: this.displayName,
                isEb: this.isEb,
                canReceiveShifts: this.canReceiveShifts
            };
        }
    }

    window.FlzPlaner.models.Assistant = Assistant;
})();
