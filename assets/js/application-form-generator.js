(function () {
    'use strict';

    // All application-information modals use the same official-form endpoint.
    // The endpoint chooses the correct template from the saved application type.
    window.openOfficialApplicationForm = function (applicationId) {
        if (!applicationId) return;
        window.open(`../api/export_application_pdf.php?id=${encodeURIComponent(applicationId)}`, '_blank');
    };
})();
