(function () {
    'use strict';
    const documentDefinitions = [
        ['proof_of_address', 'Proof of Address', app => app.has_proof_of_address],
        ['id_image', 'ID / Identification Photo', app => app.has_id_image],
        ['psa_birth_cert', 'PSA Birth Certificate', app => app.psa_birth_cert],
        ['barangay_residency', 'Barangay Residency Certificate', app => app.barangay_residency],
        ['comelec_cert', 'COMELEC Certificate', app => app.comelec_cert],
        ['proof_of_life', 'Proof of Life (Bedridden)', app => app.proof_of_life],
        ['auth_letter', 'Authorization Letter', app => app.auth_letter],
        ['proxy_id', 'Representative Government ID', app => app.proxy_id],
        ['proxy_birth_cert', 'Representative Birth Certificate', app => app.proxy_birth_cert],
        ['home_visitation_form', 'Home Visitation Form', app => app.home_visitation_form],
        ['landbank_enrollment_form', 'Land Bank Enrollment Form', app => app.landbank_enrollment_form]
    ];

    window.renderApplicationDocuments = function (app, appId) {
        const storedDocuments = Array.isArray(app.documents) ? app.documents : [];
        const files = documentDefinitions.filter(([, , exists]) => Boolean(exists(app)));
        const type = (app.application_type || 'application').replace(/_/g, ' ');
        if (!storedDocuments.length && !files.length) return `<section class="application-documents"><h3 class="application-documents__title"><i class="fas fa-folder-open"></i> Submitted Documents</h3><div class="application-documents__empty">No documents were submitted for this ${type} application.</div></section>`;

        const cards = storedDocuments.length
            ? storedDocuments.map(document => {
                const label = document.document_label || 'Submitted Document';
                const url = `../api/get_document.php?id=${encodeURIComponent(appId)}&document_id=${encodeURIComponent(document.id)}&v=${Date.now()}`;
                const isPdf = document.mime_type === 'application/pdf';
                const preview = isPdf ? '<i class="fas fa-file-pdf" aria-hidden="true"></i>' : `<img src="${url}" alt="${label}">`;
                return `<article class="application-document"><div class="application-document__name">${label}</div><div class="application-document__preview">${preview}</div><a class="btn btn-primary btn-small application-document__view" href="${url}" target="_blank"><i class="fas fa-eye"></i> View</a></article>`;
            }).join('')
            : files.map(([key, label, exists]) => {
            const rawValue = exists(app);
            // Bust the browser cache after a document replacement. The endpoint
            // intentionally remains the same so permissions and MIME handling
            // stay centralized.
            const url = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${encodeURIComponent(key)}&v=${Date.now()}`;
            const isPdf = typeof rawValue === 'string' && rawValue.toLowerCase().endsWith('.pdf');
            const preview = isPdf ? '<i class="fas fa-file-pdf" aria-hidden="true"></i>' : `<img src="${url}" alt="${label}">`;
            return `<article class="application-document"><div class="application-document__name">${label}</div><div class="application-document__preview">${preview}</div><a class="btn btn-primary btn-small application-document__view" href="${url}" target="_blank"><i class="fas fa-eye"></i> View</a></article>`;
        }).join('');
        return `<section class="application-documents"><h3 class="application-documents__title"><i class="fas fa-folder-open"></i> Submitted Documents</h3><p class="application-documents__hint">Files submitted with this application. Open a document to inspect it at full size.</p><div class="application-documents__grid">${cards}</div></section>`;
    };
})();
