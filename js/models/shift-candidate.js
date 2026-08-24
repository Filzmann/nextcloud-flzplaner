(function() {
    const { Model } = window.LocalBase.models;
    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.models = window.ADPlaner.models || {};

    class ShiftCandidate extends Model {
        constructor(data = {}) {
            super();
            this.id = data.id ?? 0;
            this.slotId = data.slotId ?? data.slot_id ?? 0;
            this.assistantUid = data.assistantUid || data.assistant_uid || data.uid || '';
            this.uid = this.assistantUid;
            this.displayName = data.displayName || data.display_name || this.uid;
            this.isSelf = !!(data.isSelf ?? data.is_self ?? false);
        }

        toArray() {
            return {
                id: this.id,
                slotId: this.slotId,
                assistantUid: this.assistantUid,
                uid: this.uid,
                displayName: this.displayName,
                isSelf: this.isSelf
            };
        }
    }

    window.ADPlaner.models.ShiftCandidate = ShiftCandidate;
})();
