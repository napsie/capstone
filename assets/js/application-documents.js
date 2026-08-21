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

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);

    const uniqueDocuments = documents => {
        const documentsByRequirement = new Map();
        documents.forEach((document, index) => {
            const requirement = String(document.document_key || document.document_label || `document-${document.id || index}`)
                .trim().toLowerCase();
            const current = documentsByRequirement.get(requirement);
            if (!current || Number(document.id || 0) >= Number(current.id || 0)) {
                documentsByRequirement.set(requirement, document);
            }
        });
        return Array.from(documentsByRequirement.values());
    };

    window.renderApplicationDocuments = function (app, appId, options = {}) {
        const storedDocuments = uniqueDocuments(Array.isArray(app.documents) ? app.documents : []);
        const files = documentDefinitions.filter(([, , exists]) => Boolean(exists(app)));
        const type = escapeHtml((app.application_type || 'application').replace(/_/g, ' '));
        if (!storedDocuments.length && !files.length) return `<section class="application-documents"><div class="application-documents__header"><div><h3 class="application-documents__title"><i class="fas fa-folder-open" aria-hidden="true"></i> Submitted Documents</h3><p class="application-documents__hint">Documents attached to this application.</p></div><span class="application-documents__count">0 files</span></div><div class="application-documents__empty"><i class="fas fa-file-circle-xmark" aria-hidden="true"></i><span>No documents were submitted for this ${type} application.</span></div></section>`;

        const replacementControls = (documentId = '', documentKey = '') => {
            if (!options.allowReplacement) return { input: '', button: '' };
            const attributes = documentId
                ? `data-document-id="${Number(documentId)}"`
                : `data-document-key="${documentKey}"`;
            return {
                input: `<div class="application-document__replacement">
                <label>Replace document<input type="file" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="window.previewDocumentReplacement(this)"></label>
                </div>`,
                button: `<button type="button" class="application-document__replace-button" ${attributes} data-application-id="${appId}" onclick="window.replaceSubmittedDocument(this)"><i class="fas fa-upload"></i> Save replacement</button>`
            };
        };

        const cards = storedDocuments.length
            ? storedDocuments.map(document => {
                const label = escapeHtml(document.document_label || 'Submitted Document');
                const url = `../api/get_document.php?id=${encodeURIComponent(appId)}&document_id=${encodeURIComponent(document.id)}&v=${Date.now()}`;
                const isPdf = document.mime_type === 'application/pdf';
                const preview = isPdf ? '<i class="fas fa-file-pdf" aria-hidden="true"></i>' : `<img src="${url}" alt="${label}">`;
                const replacement = replacementControls(document.id);
                const fileType = isPdf ? 'PDF' : 'Image';
                return `<article class="application-document"><div class="application-document__meta"><div class="application-document__name">${label}</div><span class="application-document__type">${fileType}</span></div><div class="application-document__preview">${preview}</div>${replacement.input}<div class="application-document__actions${replacement.button ? ' has-replacement' : ''}"><a class="btn btn-primary btn-small application-document__view" href="${url}" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square" aria-hidden="true"></i> Open document</a>${replacement.button}</div></article>`;
            }).join('')
            : files.map(([key, label, exists]) => {
            const rawValue = exists(app);
            // Bust the browser cache after a document replacement. The endpoint
            // intentionally remains the same so permissions and MIME handling
            // stay centralized.
            const url = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${encodeURIComponent(key)}&v=${Date.now()}`;
            const isPdf = typeof rawValue === 'string' && rawValue.toLowerCase().endsWith('.pdf');
            const preview = isPdf ? '<i class="fas fa-file-pdf" aria-hidden="true"></i>' : `<img src="${url}" alt="${label}">`;
            const canReplaceLegacy = key === 'proof_of_address' || key === 'id_image';
            const replacement = canReplaceLegacy ? replacementControls('', key) : { input: '', button: '' };
            const fileType = isPdf ? 'PDF' : 'Image';
            return `<article class="application-document"><div class="application-document__meta"><div class="application-document__name">${label}</div><span class="application-document__type">${fileType}</span></div><div class="application-document__preview">${preview}</div>${replacement.input}<div class="application-document__actions${replacement.button ? ' has-replacement' : ''}"><a class="btn btn-primary btn-small application-document__view" href="${url}" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square" aria-hidden="true"></i> Open document</a>${replacement.button}</div></article>`;
        }).join('');
        const hint = options.allowReplacement
            ? 'Choose the correct file under the document that needs correction, then select Save replacement.'
            : 'Files submitted with this application. Open a document to inspect it at full size.';
        const count = storedDocuments.length || files.length;
        return `<section class="application-documents"><div class="application-documents__header"><div><h3 class="application-documents__title"><i class="fas fa-folder-open" aria-hidden="true"></i> Submitted Documents</h3><p class="application-documents__hint">${hint}</p></div><span class="application-documents__count">${count} ${count === 1 ? 'file' : 'files'}</span></div><div class="application-documents__grid">${cards}</div></section>`;
    };
})();
