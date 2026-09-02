(function() {
    const { Notice, byId, esc } = window.LocalBase.ui;
    const notice = new Notice('adp-notice');
    const weekday = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    const monthName = ['', 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    function dateShort(date) {
        const parts = String(date).split('-');
        if (parts.length !== 3) {
            return date;
        }
        return parts[2] + '.' + parts[1] + '.';
    }

    function dayHeader(day) {
        return esc(weekday[day.weekday] || '') + '<span>' + esc(String(day.dayOfMonth)) + '</span>';
    }

    function monthHeader(day) {
        const label = monthName[day.month] || String(day.month);
        return esc(label) + '<span>' + esc(String(day.dayOfMonth)) + '</span>';
    }

    function statusLabel(status) {
        if (status === 'approved') {
            return 'genehmigt';
        }
        if (status === 'planned') {
            return 'geplant';
        }
        return '';
    }

    function renderCapacity(countValue, minimumValue, maximumValue) {
        const count = Number(countValue) || 0;
        const minimum = finiteOrNull(minimumValue);
        const maximum = finiteOrNull(maximumValue);
        if (!hasCapacityLimits(minimum, maximum)) {
            return `<span class="adp-capacity adp-capacity--plain" title="${esc(count)} Schichten">${esc(count)}</span>`;
        }
        if (minimum !== null && count < minimum) {
            return `<span class="adp-capacity adp-capacity--under" title="${esc(count)} von mindestens ${esc(minimum)}">&lt;${esc(minimum)}</span>`;
        }
        if (maximum !== null) {
            const status = count > maximum ? 'over' : 'within';
            return `<span class="adp-capacity adp-capacity--${status}" title="${esc(count)} von maximal ${esc(maximum)}">${esc(count)}/${esc(maximum)}</span>`;
        }
        return `<span class="adp-capacity adp-capacity--within" title="${esc(count)} Schichten">${esc(count)}</span>`;
    }

    function hasCapacityLimits(minimumValue, maximumValue) {
        const minimum = finiteOrNull(minimumValue);
        const maximum = finiteOrNull(maximumValue);
        return !((minimum === null && maximum === null) || (minimum === 0 && maximum === 0));
    }

    function finiteOrNull(value) {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    function showNotice(message) {
        notice.show(message);
    }

    function showError(error, fallback = 'Die Aktion konnte nicht ausgeführt werden.') {
        notice.error(error, fallback);
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.ui = { byId, esc, dateShort, dayHeader, monthHeader, statusLabel, renderCapacity, hasCapacityLimits, showNotice, showError };
})();
