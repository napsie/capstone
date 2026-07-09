<?php
/** Inline required-documents upload block — mounted inside active official form card */
?>
<div id="requiredDocumentsSection" class="required-docs-inline" style="display:none;">
    <div class="required-docs-inner">
        <div class="required-docs-header">
            <i class="fas fa-file-upload"></i>
            <span>Required Documents</span>
        </div>
        <p class="required-docs-hint">
            <i class="fas fa-info-circle"></i>
            Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
        </p>
        <div class="required-docs-grid">
            <div class="required-docs-field">
                <label for="proofOfAddress" id="labelProofOfAddress">Proof of Address</label>
                <input type="file" id="proofOfAddress" name="proofOfAddress" required
                       accept="image/jpeg,image/png,image/gif,application/pdf"
                       onchange="checkFileSize(this)">
                <div id="proofOfAddressSizeWarn" class="required-docs-warn" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                </div>
                <div class="required-docs-preview" id="proofOfAddressPreview"></div>
            </div>
            <div class="required-docs-field">
                <label for="idImage" id="labelIdImage">ID Image / Supporting Document Photo</label>
                <input type="file" id="idImage" name="idImage" required
                       accept="image/jpeg,image/png,image/gif,application/pdf"
                       onchange="checkFileSize(this)">
                <div id="idImageSizeWarn" class="required-docs-warn" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                </div>
                <div class="required-docs-preview" id="idImagePreview"></div>
            </div>
        </div>
    </div>
</div>
