(function() {
    'use strict';

    const ui = window.ADPlaner.ui;
    const shifts = window.ADPlaner.shiftSettingsList;
    const app = new window.ADPlaner.PlanApp({
        repository: new window.ADPlaner.repositories.PlanRepository(window.ADPlaner.api),
        PlanChrome: window.ADPlaner.PlanChrome,
        PlanPanel: window.ADPlaner.PlanPanel,
        byId: ui.byId, esc: ui.esc, showNotice: ui.showNotice, showError: ui.showError,
        renderMonth: window.ADPlaner.monthPlan.render,
        renderWorkload: window.ADPlaner.workloadPanel.render,
        renderSettings: window.ADPlaner.settingsPanel.render,
        addShiftRow: shifts.addRow, removeShiftRow: shifts.removeRow, collectShifts: shifts.collect,
        openAssignmentPicker: window.ADPlaner.assignmentControl.open,
    });
    app.init();
})();
