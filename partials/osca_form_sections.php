<?php
/** Shared OSCA form field sections — used by new_application.php and submit_application.php */
$prefix = $formFieldPrefix ?? '';
$id = fn($name) => $prefix . $name;
?>
<!-- Personal Details (OSCA) -->
<div class="form-section osca-personal-section">
    <h3><i class="fas fa-id-card"></i> Personal Details (OSCA Form)</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('placeOfBirth'); ?>">Place of Birth</label>
            <input type="text" id="<?php echo $id('placeOfBirth'); ?>" name="placeOfBirth">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('gender'); ?>">Gender</label>
            <select id="<?php echo $id('gender'); ?>" name="gender">
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('civilStatus'); ?>">Civil Status</label>
            <select id="<?php echo $id('civilStatus'); ?>" name="civilStatus">
                <option value="">Select</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widow/er">Widow/er</option>
                <option value="Separated">Separated</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?php echo $id('mothersMaidenName'); ?>">Mother's Maiden Name</label>
            <input type="text" id="<?php echo $id('mothersMaidenName'); ?>" name="mothersMaidenName">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('houseNo'); ?>">House / Lot / Block No.</label>
            <input type="text" id="<?php echo $id('houseNo'); ?>" name="houseNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('street'); ?>">Street / Road / Purok</label>
            <input type="text" id="<?php echo $id('street'); ?>" name="street">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('city'); ?>">City / Municipality</label>
            <input type="text" id="<?php echo $id('city'); ?>" name="city" value="Pasig City">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('province'); ?>">Province</label>
            <input type="text" id="<?php echo $id('province'); ?>" name="province" value="Metro Manila">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('zipCode'); ?>">ZIP Code</label>
            <input type="text" id="<?php echo $id('zipCode'); ?>" name="zipCode" maxlength="10">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('landmark'); ?>">Landmark</label>
            <input type="text" id="<?php echo $id('landmark'); ?>" name="landmark">
        </div>
    </div>
</div>

<!-- F1 Senior ID -->
<div id="<?php echo $prefix; ?>senior-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-address-card"></i> F1 — Senior ID Application</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('idPurpose'); ?>">Purpose</label>
            <select id="<?php echo $id('idPurpose'); ?>" name="idPurpose">
                <option value="new">New</option>
                <option value="lost">Lost</option>
                <option value="change">Change</option>
                <option value="transfer">Transfer</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?php echo $id('healthStatus'); ?>">Health Status</label>
            <select id="<?php echo $id('healthStatus'); ?>" name="healthStatus">
                <option value="">Select</option>
                <option value="Physically Fit">Physically Fit</option>
                <option value="Bedridden">Bedridden</option>
                <option value="Frail/Sickly">Frail/Sickly</option>
                <option value="PWD">PWD</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="<?php echo $id('seniorIdNo'); ?>">Senior ID No. (if replacement/transfer)</label>
        <input type="text" id="<?php echo $id('seniorIdNo'); ?>" name="seniorIdNo">
    </div>
</div>

<!-- F2 Landbank -->
<div id="<?php echo $prefix; ?>landbank-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-credit-card"></i> F2 — Land Bank Cash Card Enrollment</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('nameOnCard'); ?>">Name to Appear on Card (max 23 chars)</label>
            <input type="text" id="<?php echo $id('nameOnCard'); ?>" name="nameOnCard" maxlength="23">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('tin'); ?>">TIN</label>
            <input type="text" id="<?php echo $id('tin'); ?>" name="tin">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('idTypePresented'); ?>">Type of ID Presented</label>
            <input type="text" id="<?php echo $id('idTypePresented'); ?>" name="idTypePresented" value="OSCA">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('sourceOfFunds'); ?>">Source of Funds</label>
            <input type="text" id="<?php echo $id('sourceOfFunds'); ?>" name="sourceOfFunds">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('seniorIdNoLandbank'); ?>">Senior ID No.</label>
            <input type="text" id="<?php echo $id('seniorIdNoLandbank'); ?>" name="seniorIdNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('nationality'); ?>">Nationality</label>
            <input type="text" id="<?php echo $id('nationality'); ?>" name="nationality" value="Filipino">
        </div>
    </div>
</div>

<!-- F5 Milestone -->
<div id="<?php echo $prefix; ?>milestone-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-gift"></i> F5 — Octogenarian / Nonagenarian / Centenarian</h3>
    <div class="form-group">
        <label for="<?php echo $id('milestoneAge'); ?>">Milestone Age</label>
        <select id="<?php echo $id('milestoneAge'); ?>" name="milestoneAge">
            <option value="">Select</option>
            <option value="80">80 years old — ₱10,000</option>
            <option value="85">85 years old — ₱15,000</option>
            <option value="90">90 years old — ₱25,000</option>
            <option value="95">95 years old — ₱25,000</option>
            <option value="100">100 years old — ₱25,000</option>
        </select>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('claimantName'); ?>">Family Representative / Claimant Name</label>
            <input type="text" id="<?php echo $id('claimantName'); ?>" name="claimantName">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('claimantRelationship'); ?>">Relationship</label>
            <input type="text" id="<?php echo $id('claimantRelationship'); ?>" name="claimantRelationship">
        </div>
    </div>
    <div class="form-group">
        <label for="<?php echo $id('claimantContact'); ?>">Claimant Contact No.</label>
        <input type="text" id="<?php echo $id('claimantContact'); ?>" name="claimantContact">
    </div>
</div>

<!-- Pension economic status -->
<div id="<?php echo $prefix; ?>pension-extra-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-coins"></i> Local Senior Pension — Economic Status</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('seniorIdNoPension'); ?>">Senior ID No.</label>
            <input type="text" id="<?php echo $id('seniorIdNoPension'); ?>" name="seniorIdNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('atmCardNo'); ?>">ATM / Temp Cash Card Stub No.</label>
            <input type="text" id="<?php echo $id('atmCardNo'); ?>" name="atmCardNo">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('controlNo'); ?>">Control No.</label>
            <input type="text" id="<?php echo $id('controlNo'); ?>" name="controlNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('pensionSource'); ?>">Pension Source (if pensioner)</label>
            <input type="text" id="<?php echo $id('pensionSource'); ?>" name="pensionSource">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('incomeSource'); ?>">Permanent Income Source</label>
            <input type="text" id="<?php echo $id('incomeSource'); ?>" name="incomeSource">
        </div>
        <div class="form-group">
            <label>Own House?</label>
            <select name="ownsHouse" id="<?php echo $id('ownsHouse'); ?>">
                <option value="">—</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Renter?</label>
            <select name="isRenter" id="<?php echo $id('isRenter'); ?>">
                <option value="">—</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?php echo $id('healthConditionPension'); ?>">Condition / Illness</label>
            <input type="text" id="<?php echo $id('healthConditionPension'); ?>" name="healthCondition">
        </div>
    </div>
</div>

<!-- F7 Burial extended -->
<div id="<?php echo $prefix; ?>burial-extra-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-cross"></i> F7 — Burial Assistance (Deceased Info)</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('deceasedLastName'); ?>">Deceased Last Name</label>
            <input type="text" id="<?php echo $id('deceasedLastName'); ?>" name="deceasedLastName">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('deceasedFirstName'); ?>">Deceased First Name</label>
            <input type="text" id="<?php echo $id('deceasedFirstName'); ?>" name="deceasedFirstName">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('deceasedMiddleName'); ?>">Deceased Middle Name</label>
            <input type="text" id="<?php echo $id('deceasedMiddleName'); ?>" name="deceasedMiddleName">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('deceasedSuffix'); ?>">Deceased Suffix</label>
            <input type="text" id="<?php echo $id('deceasedSuffix'); ?>" name="deceasedSuffix">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('deceasedBirthDate'); ?>">Birth Date of Deceased</label>
            <input type="date" id="<?php echo $id('deceasedBirthDate'); ?>" name="deceasedBirthDate">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('seniorIdNoBurial'); ?>">Senior ID No. (Deceased)</label>
            <input type="text" id="<?php echo $id('seniorIdNoBurial'); ?>" name="seniorIdNo">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('landbankCardNo'); ?>">Landbank Cash Card No.</label>
            <input type="text" id="<?php echo $id('landbankCardNo'); ?>" name="landbankCardNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('applicantName'); ?>">Name of Applicant / Claimant</label>
            <input type="text" id="<?php echo $id('applicantName'); ?>" name="applicantName">
        </div>
    </div>
</div>

<!-- F8 Home Visit -->
<div id="<?php echo $prefix; ?>homevisit-fields" class="form-section osca-type-fields" style="display:none;">
    <h3><i class="fas fa-home"></i> F8 — Home Visitation / Confirmation</h3>
    <div class="form-group">
        <label>Visit Purpose</label>
        <div class="checkbox-group">
            <label><input type="checkbox" name="visit_purpose[]" value="LOCAL PENSION"> Local Pension</label>
            <label><input type="checkbox" name="visit_purpose[]" value="BURIAL ASSISTANCE"> Burial Assistance</label>
            <label><input type="checkbox" name="visit_purpose[]" value="SENIOR ID"> Senior ID</label>
            <label><input type="checkbox" name="visit_purpose[]" value="CASH GIFT"> Cash Gift</label>
            <label><input type="checkbox" name="visit_purpose[]" value="OCTOGENARIAN"> Octogenarian</label>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('seniorIdNoVisit'); ?>">SC ID No.</label>
            <input type="text" id="<?php echo $id('seniorIdNoVisit'); ?>" name="seniorIdNo">
        </div>
        <div class="form-group">
            <label for="<?php echo $id('livingArrangement'); ?>">Living Arrangement</label>
            <select id="<?php echo $id('livingArrangement'); ?>" name="livingArrangement">
                <option value="">Select</option>
                <option value="OWNED">Owned</option>
                <option value="LIVING ALONE">Living Alone</option>
                <option value="LIVING WITH RELATIVES">Living with Relatives</option>
                <option value="RENT">Rent</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Pensioner?</label>
            <select name="isPensioner" id="<?php echo $id('isPensioner'); ?>">
                <option value="">—</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?php echo $id('pensionSourceVisit'); ?>">If yes, source and amount</label>
            <input type="text" id="<?php echo $id('pensionSourceVisit'); ?>" name="pensionSource">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="<?php echo $id('healthCondition'); ?>">Health Condition / Illness</label>
            <input type="text" id="<?php echo $id('healthCondition'); ?>" name="healthCondition">
        </div>
        <div class="form-group">
            <label>With Maintenance?</label>
            <select name="withMaintenance" id="<?php echo $id('withMaintenance'); ?>">
                <option value="">—</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="<?php echo $id('maintenanceSpec'); ?>">Maintenance (specify if yes)</label>
        <input type="text" id="<?php echo $id('maintenanceSpec'); ?>" name="maintenanceSpec">
    </div>
    <div class="form-group">
        <label for="<?php echo $id('visitSummary'); ?>">Visit Summary</label>
        <textarea id="<?php echo $id('visitSummary'); ?>" name="visitSummary" rows="4"></textarea>
    </div>
</div>
