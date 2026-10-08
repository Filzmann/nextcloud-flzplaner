(function() {
    'use strict';

    const ui = window.FlzPlaner.ui;
    const shifts = window.FlzPlaner.shiftSettingsList;
    const app = new window.FlzPlaner.PlanApp({
        repository: new window.FlzPlaner.repositories.PlanRepository(window.FlzPlaner.api),
        PlanChrome: window.FlzPlaner.PlanChrome,
        PlanPanel: window.FlzPlaner.PlanPanel,
        byId: ui.byId, esc: ui.esc, showNotice: ui.showNotice, showError: ui.showError,
        renderMonth: window.FlzPlaner.monthPlan.render,
        renderWorkload: window.FlzPlaner.workloadPanel.render,
        renderSettings: window.FlzPlaner.settingsPanel.render,
        addShiftRow: shifts.addRow, removeShiftRow: shifts.removeRow, collectShifts: shifts.collect,
        openAssignmentPicker: window.FlzPlaner.assignmentControl.open,
    });
    app.init();
})();
