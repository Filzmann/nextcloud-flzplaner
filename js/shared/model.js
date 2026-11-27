(function() {
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    window.OCA = window.OCA || {};
    window.OCA.FlzPlaner = window.OCA.FlzPlaner || {};
    window.OCA.FlzPlaner.Shared = window.OCA.FlzPlaner.Shared || {};
    const shared = window.OCA.FlzPlaner.Shared;

    class Model {
        static get(data) {
            if (data == null) {
                return null;
            }
            return data instanceof this ? data : new this(data || {});
        }

        static get_all(items = []) {
            if (!Array.isArray(items)) {
                return [];
            }
            return items.map(item => this.get(item)).filter(Boolean);
        }

        toArray() {
            throw new Error(`${this.constructor.name} ${translate('flzplaner', 'muss toArray implementieren.')}`);
        }

        to_array() {
            return this.toArray();
        }

        save() {
            throw new Error(`${this.constructor.name} ${translate('flzplaner', 'kann nicht direkt gespeichert werden.')}`);
        }
    }

    shared.models = shared.models || {};
    shared.models.Model = Model;
})();
