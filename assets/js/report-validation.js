document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('exportReportForm');
    if (!form) return;

    const dateFrom = form.querySelector('[name="date_from"]');
    const dateTo = form.querySelector('[name="date_to"]');
    const year = form.querySelector('[name="year"]');
    const format = form.querySelector('[name="format"]');
    const selectedBarangayChoice = form.querySelector('input[name="barangayChoice"][value="selected"]');
    const barangaySelect = document.getElementById('exportBarangay');
    const today = new Date();
    const todayValue = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');

    [dateFrom, dateTo].forEach(input => {
        if (input) input.max = todayValue;
    });

    const clearValidation = () => {
        dateFrom?.setCustomValidity('');
        dateTo?.setCustomValidity('');
        barangaySelect?.setCustomValidity('');
        format?.setCustomValidity('');
    };

    const syncPeriodControls = () => {
        const hasDate = Boolean(dateFrom?.value || dateTo?.value);
        if (year && hasDate) year.value = 'all';
    };

    const validateReport = () => {
        clearValidation();
        const from = dateFrom?.value || '';
        const to = dateTo?.value || '';

        if (!format || !['pdf', 'excel'].includes(format.value)) {
            format?.setCustomValidity('Choose PDF or Excel for the report.');
            format?.reportValidity();
            return false;
        }

        if (!from || !to) {
            const missingInput = !from ? dateFrom : dateTo;
            missingInput?.setCustomValidity('Select both the start and end dates for the report period.');
            missingInput?.reportValidity();
            return false;
        }
        if (from && to && from > to) {
            dateTo.setCustomValidity('The end date must be the same as or later than the start date.');
            dateTo.reportValidity();
            return false;
        }
        if ((from && from > todayValue) || (to && to > todayValue)) {
            const futureInput = from > todayValue ? dateFrom : dateTo;
            futureInput.setCustomValidity('Report dates cannot be in the future.');
            futureInput.reportValidity();
            return false;
        }
        if (selectedBarangayChoice?.checked && !barangaySelect?.value) {
            barangaySelect?.setCustomValidity('Select a barangay for this report.');
            barangaySelect?.reportValidity();
            return false;
        }
        return form.checkValidity();
    };

    [dateFrom, dateTo].forEach(input => {
        input?.addEventListener('input', () => {
            clearValidation();
            syncPeriodControls();
        });
    });
    barangaySelect?.addEventListener('change', clearValidation);
    format?.addEventListener('change', clearValidation);
    form.addEventListener('submit', event => {
        syncPeriodControls();
        if (!validateReport()) event.preventDefault();
    });
    syncPeriodControls();
});
