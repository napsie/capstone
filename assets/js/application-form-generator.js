(function () {
    'use strict';

    const formLabels = {
        senior: 'Senior Citizens ID Application',
        landbank: 'Land Bank Cash Card Enrollment',
        pension: 'Local Senior Pension Form',
        national_pension: 'National DSWD Pension Form',
        milestone_gift: 'Milestone Gift Application',
        burial: 'Burial Assistance Form',
        home_visit: 'Home Visitation / Confirmation Form'
    };

    window.getOfficialApplicationFormLabel = function (applicationType) {
        return formLabels[applicationType] || 'Official Application Form';
    };

    // All application-information modals use the same official-form endpoint.
    // The endpoint chooses the correct template from the saved application type.
    window.openOfficialApplicationForm = function (applicationId) {
        if (!applicationId) return;
        window.open(`../api/export_application_pdf.php?id=${encodeURIComponent(applicationId)}`, '_blank');
    };
})();
