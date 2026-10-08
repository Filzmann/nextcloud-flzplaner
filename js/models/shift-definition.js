(function() {
    const { Model } = window.LocalBase.models;
    window.FlzPlaner = window.FlzPlaner || {};
    window.FlzPlaner.models = window.FlzPlaner.models || {};

    class ShiftDefinition extends Model {
        constructor(data = {}) {
            super();
            this.key = data.key || '';
            this.label = data.label || '';
            this.startsAt = data.startsAt || data.starts_at || '';
            this.endsAt = data.endsAt || data.ends_at || '';
            this.enabled = data.enabled ?? true;
        }

        toArray() {
            return {
                key: this.key,
                label: this.label,
                startsAt: this.startsAt,
                endsAt: this.endsAt,
                enabled: this.enabled
            };
        }
    }

    window.FlzPlaner.models.ShiftDefinition = ShiftDefinition;
})();
