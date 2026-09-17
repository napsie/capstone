// Run with: node tests/workflow.integration.cjs [--serve]
// Requires the local XAMPP PHP and MySQL services. Creates and removes an isolated DB.
const { spawn, spawnSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'D:/xampp1/php/php.exe';
const testMasterPassword = 'TestOnlyMaster!2026';
const masterHashResult = spawnSync(php, ['-r', `echo password_hash('${testMasterPassword}', PASSWORD_DEFAULT);`], { encoding: 'utf8', windowsHide: true });
if (masterHashResult.status !== 0) throw Error(masterHashResult.stderr || 'Unable to create test master password hash');
const database = 'seniorlink_test_' + randomBytes(6).toString('hex');
const base = 'http://127.0.0.1:8765';
const sessions = fs.mkdtempSync(path.join(os.tmpdir(), 'seniorlink-test-'));
const privateStorage = fs.mkdtempSync(path.join(os.tmpdir(), 'seniorlink-private-test-'));
fs.mkdirSync(path.join(privateStorage, 'uploads'));
let server, passed = 0;
let uploadedSystemLogo = '';
const fixture = mode => {
    const result = spawnSync(php, [path.join(__dirname, 'workflow_fixture.php'), mode, database], { cwd: root, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
    return result.stdout.trim();
};
const check = (condition, label) => { assert.ok(condition, label); passed++; console.log('PASS ' + label); };
async function request(url, cookie = '', body, raw = false) {
    const response = await fetch(base + url, {
        method: body ? 'POST' : 'GET', redirect: 'manual',
        headers: { ...(cookie ? { Cookie: cookie } : {}), ...(body ? { Origin: base } : {}) }, body,
    });
    const text = await response.text();
    let data = text;
    if (!raw) { try { data = JSON.parse(text); } catch { throw Error(url + ': ' + text.slice(0, 1800)); } }
    return { status: response.status, headers: response.headers, data };
}
async function login(name, department = false, barangay = 'Bagong Ilog') {
    const result = await request('/index.php', '', new URLSearchParams({ login_role: department ? 'department_admin' : 'barangay_staff', username: name, password: 'TestOnly!2026', barangay, ...(department ? { remember: 'on' } : {}) }), true);
    check(result.status === 302, 'Login ' + name);
    check(result.headers.get('location') === (department ? 'pages/department_dashboard.php' : 'pages/barangay_dash.php'), 'Login redirects to existing dashboard');
    if (department) check(result.headers.getSetCookie().some(cookie => cookie.startsWith('remember_me=')), 'Remembered login token is stored');
    return result.headers.getSetCookie().map(cookie => cookie.split(';')[0]).join('; ');
}
const action = (cookie, id, action, fields = {}) => request('/api/update_workflow_status.php', cookie, new URLSearchParams({ applicationId: id, action, ...fields }));
async function main() {
    fixture('setup');
    server = spawn(php, ['-d', 'session.save_path=' + sessions, '-d', 'upload_tmp_dir=' + sessions, '-S', '127.0.0.1:8765', '-t', root], {
        cwd: root, env: { ...process.env, DATABASE_URL: `mysql://root@localhost/${database}`, SENIORLINK_PRIVATE_STORAGE: privateStorage, MASTER_PASSWORD_HASH: masterHashResult.stdout.trim() }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
    });
    let serverErrors = '';
    server.stderr.on('data', data => { serverErrors += data; });
    for (let n = 0; n < 40; n++) {
        if (server.exitCode !== null) throw Error(serverErrors);
        try { await fetch(base + '/index.php'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    const admin = await login('test-admin', true);
    const staff = await login('test-staff');
    const other = await login('test-other', false, 'Ugong');
    const signup = await request('/pages/signup.php', '', new URLSearchParams({ firstName: 'New', lastName: 'Staff', email: 'new-staff@example.invalid', phone: '09170001111', username: 'test-signup', password: 'TestOnly!2026', confirmPassword: 'TestOnly!2026', role: 'barangay_staff', barangay: 'Bambang', masterPassword: testMasterPassword }), true);
    check(signup.status === 200 && signup.data.includes('User registered successfully'), 'Staff account can be created');
    const createdStaff = await login('test-signup', false, 'Bambang');
    check((await request('/pages/barangay_dash.php', createdStaff, undefined, true)).status === 200, 'Created staff account opens its dashboard');
    const adminSignup = await request('/pages/signup.php', '', new URLSearchParams({ firstName: 'New', lastName: 'Admin', email: 'new-admin@example.invalid', phone: '09170002222', username: 'test-new-admin', password: 'TestOnly!2026', confirmPassword: 'TestOnly!2026', role: 'department_admin', masterPassword: testMasterPassword }), true);
    check(adminSignup.status === 200 && adminSignup.data.includes('User registered successfully'), 'Administrator account can be created');
    const createdAdmin = await login('test-new-admin', true);
    check((await request('/pages/department_dashboard.php', createdAdmin, undefined, true)).status === 200, 'Created administrator opens its dashboard');
    const releaseDate = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
    const laterReleaseDate = new Date(Date.now() + 5 * 86400000).toISOString().slice(0, 10);
    const releaseForm = (id, date, action = 'set') => new URLSearchParams({ applicationId: id, expectedReleaseDate: date, action });
    check((await request('/api/set_release_date.php', '', releaseForm('PRX-BENE', releaseDate))).status === 403,
        'Anonymous user cannot schedule a release');
    check((await request('/api/set_release_date.php', staff, releaseForm('PRX-BENE', releaseDate))).status === 403,
        'Barangay staff cannot schedule a release');
    check((await request('/api/set_release_date.php', admin, releaseForm('CORRECT', releaseDate))).status === 409,
        'Unverified application cannot receive a release date');
    check((await request('/api/set_release_date.php', admin, releaseForm('ARCHIVED', releaseDate))).status === 409,
        'Archived application cannot receive a release date');
    check((await request('/api/set_release_date.php', admin, releaseForm('PRX-BENE', '2000-01-01'))).status === 400,
        'Past expected release date is rejected');
    const scheduled = await request('/api/set_release_date.php', admin, releaseForm('PRX-BENE', releaseDate));
    check(scheduled.data.success && scheduled.data.expected_release_date === releaseDate,
        'Department administrator sets an expected release date');
    check((await request('/api/get_application_details.php?id=PRX-BENE', admin)).data.expected_release_date === releaseDate,
        'Verified record details show the saved date');
    const scheduledTracker = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', '', undefined, true);
    check(scheduledTracker.data.includes('Expected Release Date') && scheduledTracker.data.includes('Actual Release Date')
        && scheduledTracker.data.includes(new Date(releaseDate + 'T00:00:00').getFullYear().toString()),
        'Tracker distinguishes expected and actual release dates');
    const rescheduled = await request('/api/set_release_date.php', admin, releaseForm('PRX-BENE', laterReleaseDate));
    check(rescheduled.data.success && rescheduled.data.expected_release_date === laterReleaseDate,
        'Administrator can reschedule the expected release');
    const clearedSchedule = await request('/api/set_release_date.php', admin, releaseForm('PRX-BENE', '', 'clear'));
    check(clearedSchedule.data.success && clearedSchedule.data.expected_release_date === null,
        'Administrator can remove an expected release date');
    const releaseDetails = (await request('/api/get_application_details.php?id=PRX-BENE', admin)).data;
    check(releaseDetails.workflow_state === 'Verified' && !releaseDetails.expected_release_date
        && releaseDetails.history.some(h => h.comments.includes('Expected release date changed'))
        && releaseDetails.history.some(h => h.comments.includes('Expected release date removed')),
        'Schedule edits are audited without changing verification status');
    check((await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', '', undefined, true)).data.includes('Schedule to follow'),
        'Tracker returns to schedule-to-follow after date removal');
    const settingsBefore = await request('/pages/system_settings.php', admin, undefined, true);
    check(settingsBefore.status === 200 && settingsBefore.data.includes('System and Report Logo')
        && !settingsBefore.data.includes('System Maintenance') && !settingsBefore.data.includes('Security Settings'),
        'Settings keeps the logo control and removes maintenance and security cards');
    const logoBytes = fs.readFileSync(path.join(root, 'images', 'system_logos', 'official-osca-logo.png'));
    const logoForm = new FormData();
    logoForm.set('updateSystemLogo', '1');
    logoForm.set('systemLogo', new Blob([logoBytes], { type: 'image/png' }), 'new-system-logo.png');
    const savedSettings = await request('/pages/system_settings.php', admin, logoForm, true);
    const logoNameMatch = savedSettings.data.match(/system-logo-[0-9]+-[a-f0-9]+\.png/);
    if (logoNameMatch) uploadedSystemLogo = logoNameMatch[0];
    check(savedSettings.status === 200 && savedSettings.data.includes('System logo updated.') && Boolean(uploadedSystemLogo),
        'Administrator can upload a new system logo');
    const currentLogo = await fetch(base + '/api/system_logo.php');
    check(currentLogo.status === 200 && currentLogo.headers.get('content-type') === 'image/png'
        && currentLogo.headers.get('cache-control').includes('no-cache')
        && Buffer.from(await currentLogo.arrayBuffer()).equals(logoBytes),
        'New logo is served immediately on system pages');
    const pdfReport = await request('/api/export_records_excel.php?scope=department&format=pdf', admin, undefined, true);
    check(pdfReport.status === 200 && pdfReport.headers.get('content-type') === 'application/pdf'
        && pdfReport.data.startsWith('%PDF') && pdfReport.data.includes('/Logo'),
        'PDF report includes the saved logo');
    const excelReport = await request('/api/export_records_excel.php?scope=department&format=excel', admin, undefined, true);
    check(excelReport.status === 200 && excelReport.headers.get('content-type').includes('spreadsheetml.sheet')
        && excelReport.data.startsWith('PK') && excelReport.data.includes('system-logo.png'),
        'Excel report includes the saved logo');
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-private-proof.pdf'), '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF');
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-id-photo.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==', 'base64'));
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-id-front.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==', 'base64'));
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-id-back.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==', 'base64'));
    fixture('private-upload');
    const deniedPrivateFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', '', undefined, true);
    check(deniedPrivateFile.status === 401, 'Anonymous private document access denied');
    const allowedPrivateFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', staff, undefined, true);
    check(allowedPrivateFile.status === 200 && allowedPrivateFile.data.startsWith('%PDF-1.4'), 'Authorized staff can view private document');
    const wrongBarangayFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', other, undefined, true);
    check(wrongBarangayFile.status === 404, 'Other barangay cannot view private document');
    for (const side of ['front', 'back']) {
        const photo = await request('/api/get_document.php?id=PRX-BENE&doc_type=government_id_' + side, staff, undefined, true);
        check(photo.status === 200 && photo.headers.get('content-type') === 'image/png', `Staff can view government ID ${side}`);
        check((await request('/api/get_document.php?id=PRX-BENE&doc_type=government_id_' + side, other, undefined, true)).status === 404,
            `Other barangay cannot view government ID ${side}`);
    }
    const governmentIdDetails = await request('/api/get_application_details.php?id=PRX-BENE', admin);
    check(governmentIdDetails.data.government_id_front === 'synthetic-id-front.png'
        && governmentIdDetails.data.government_id_back === 'synthetic-id-back.png', 'Both government ID sides belong to one application');
    const retiredPhotoRoute = await request('/api/digital_id_photo.php?token=PRX-BENE', '', undefined, true);
    check(retiredPhotoRoute.status === 410, 'Public token-only photo route is closed');
    const landing = await request('/index.php', '', undefined, true);
    check(landing.data.includes('id="landingTrackerToken"') && !landing.data.includes('pattern="PRX-[A-Za-z0-9]{4,12}"'), 'Landing tracker does not block typed tokens with browser pattern validation');
    const typedToken = await request('/pages/benefit_tracker.php?token=' + encodeURIComponent('prx – bene') + '&service=senior', '', undefined, true);
    check(typedToken.status === 200 && typedToken.data.includes('Benefits Portal Senior'), 'Tracker accepts manually typed lowercase, spaces, and alternate dash');
    const noDashToken = await request('/pages/benefit_tracker.php?token=prxbene&service=senior', '', undefined, true);
    check(noDashToken.status === 200 && noDashToken.data.includes('Benefits Portal Senior'), 'Tracker accepts a PRX token typed without the dash');
    const trackedId = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', '', undefined, true);
    const trackerCookie = trackedId.headers.getSetCookie().map(cookie => cookie.split(';')[0]).join('; ');
    const csrfMatch = trackedId.data.match(/name="photo_csrf" value="([a-f0-9]+)"/);
    check(trackedId.status === 200 && csrfMatch && !trackedId.data.includes('tracker_id_photo.php?id='), 'Tracker hides applicant photo before verification');
    check((await request('/api/tracker_id_photo.php?id=PRX-BENE', '', undefined, true)).status === 403, 'Applicant photo rejects anonymous requests');
    const wrongPhone = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', trackerCookie, new URLSearchParams({ reveal_photo: '1', photo_csrf: csrfMatch[1], registered_phone: '09171111111' }), true);
    check(wrongPhone.status === 200 && wrongPhone.data.includes('mobile number did not match'), 'Wrong mobile number cannot reveal photo');
    const correctPhone = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', trackerCookie, new URLSearchParams({ reveal_photo: '1', photo_csrf: csrfMatch[1], registered_phone: '09170000000' }), true);
    check(correctPhone.status === 302, 'Registered mobile number verifies photo access');
    const verifiedTracker = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=senior', trackerCookie, undefined, true);
    check(verifiedTracker.data.includes('tracker_id_photo.php?id=PRX-BENE'), 'Verified tracker renders applicant photo');
    const servedPhoto = await request('/api/tracker_id_photo.php?id=PRX-BENE', trackerCookie, undefined, true);
    check(servedPhoto.status === 200 && servedPhoto.headers.get('content-type') === 'image/png', 'Verified session can load private applicant photo');
    check((await request('/api/tracker_id_photo.php?id=VALID', trackerCookie, undefined, true)).status === 403, 'Photo access is limited to verified application');
    const seniorIdPortal = await request('/pages/proxy_registration.php', '', undefined, true);
    check(seniorIdPortal.data.includes('<option value="new"') && seniorIdPortal.data.includes('<option value="transfer"')
        && !seniorIdPortal.data.includes('<option value="change"') && !seniorIdPortal.data.includes('<option value="lost"'),
        'Senior ID portal offers only New and Transfer purposes');
    const benefitsPortal = await request('/pages/senior_benefits.php', '', new URLSearchParams({
        verify_benefit_access: '1', seniorCitizenId: 'OSCA-TEST-BENEFITS', permanentToken: 'PRX-BENE'
    }), true);
    check(benefitsPortal.data.includes('Update or Replace Senior ID') && benefitsPortal.data.includes('<option value="change"')
        && benefitsPortal.data.includes('<option value="lost"') && !benefitsPortal.data.includes('<option value="new"'),
        'Benefits portal offers Change and Lost ID purposes');
    check(benefitsPortal.data.includes('value="Test Contact"') && benefitsPortal.data.includes('value="09171234567"')
        && benefitsPortal.data.includes('<option value="Physically Fit" selected'),
        'Benefits portal prefills existing health and emergency-contact answers');
    check(benefitsPortal.data.includes('How to change your information')
        && benefitsPortal.data.includes('Go to editable information')
        && benefitsPortal.data.includes('aria-live="polite"'),
        'Information Change provides accessible step-by-step guidance');
    check(benefitsPortal.data.includes('Pension or Income Supporting Record (Optional)')
        && benefitsPortal.data.includes('"optional":true'),
        'Local Pension SSS or pension supporting record is optional');
    check(benefitsPortal.data.includes('Upload clear images of the front and back of one valid government-issued ID. PNG, JPG, or JPEG only. Maximum of 2 images.')
        && benefitsPortal.data.includes('data-id-pair-preview') && benefitsPortal.data.includes('name="valid_id_file[]"'),
        'Public form offers one front-and-back government ID upload area');
    for (const match of benefitsPortal.data.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        if (/\bsrc=/.test(match[1]) || /application\/json/.test(match[1])) continue;
        new vm.Script(match[2], { filename: 'senior_benefits.php' });
    }
    check(true, 'Public benefit form scripts parse');
    check((await request('/api/verify_senior_integrity.php?id=OSCA-TEST-VALID')).status === 401, 'Anonymous senior lookup denied');
    let result = await request('/api/verify_senior_integrity.php?id=OSCA-TEST-VALID', staff);
    check(result.data.success && !('complete_address' in result.data.senior), 'Authorized lookup returns limited profile data');
    check(!(await request('/api/verify_senior_integrity.php?id=OSCA-TEST-OTHER', staff)).data.success, 'Cross-barangay lookup denied');
    check((await request('/api/verify_senior_integrity.php?id=OSCA-TEST-OTHER', admin)).data.success, 'Department lookup covers other barangays');
    check(!(await request('/api/verify_senior_integrity.php?id=OSCA-TEST-ARCHIVED', admin)).data.success, 'Archived senior excluded');
    check(!(await action(admin, 'CORRECT', 'return', { comments: 'Blurry' })).data.success, 'Correction requires affected documents');
    check(!(await action(admin, 'CORRECT', 'return', { correctionDocuments: 'PSA' })).data.success, 'Correction requires reason');
    check(!(await action(other, 'CORRECT', 'next')).data.success, 'Cross-barangay status update denied');
    result = await action(admin, 'CORRECT', 'return', { correctionDocuments: 'PSA birth certificate', comments: 'Upload a readable copy.' });
    check(result.data.current_status === 'Needs Correction', 'Department requests correction');
    let details = (await request('/api/get_application_details.php?id=CORRECT', staff)).data;
    check(details.return_reason.includes('PSA birth certificate') && details.return_comments.includes('readable'), 'Correction instructions persist in details and history');
    check(!(await action(admin, 'CORRECT', 'next')).data.success, 'Department cannot bypass barangay correction');
    check((await request('/api/search_applications.php?status=Needs%20Correction', staff)).data.data.some(app => app.id === 'CORRECT'), 'Returned application stays in barangay queue');
    const upload = new FormData();
    upload.set('applicationId', 'CORRECT');
    upload.set('documentKey', 'psa_birth_cert');
    upload.set('replacementDocument', new Blob(['%PDF-1.4\n% Synthetic replacement\n%%EOF'], { type: 'application/pdf' }), 'corrected.pdf');
    check(!(await request('/api/update_application_documents.php', other, upload)).data.success, 'Cross-barangay document replacement denied');
    check((await request('/api/update_application_documents.php', staff, upload)).data.success, 'Legacy document replacement succeeds');
    const served = await request('/api/get_document.php?id=CORRECT&doc_type=psa_birth_cert', staff, undefined, true);
    check(served.data.includes('Synthetic replacement') && served.headers.get('content-type').includes('application/pdf'), 'Legacy document URL serves corrected content');
    const missing = new FormData(); missing.set('applicationId', 'CORRECT'); missing.set('documentLabel', 'Missing residency certificate');
    missing.set('replacementDocument', new Blob(['%PDF-1.4\n% Missing requirement\n%%EOF'], { type: 'application/pdf' }), 'missing.pdf');
    check((await request('/api/update_application_documents.php', staff, missing)).data.success, 'Missing requirement can be uploaded');
    details = (await request('/api/get_application_details.php?id=CORRECT', staff)).data;
    check(details.documents.length === 2, 'Both correction documents available');
    const documentId = details.documents[0].id;
    const replace = new FormData(); replace.set('applicationId', 'CORRECT'); replace.set('documentId', documentId);
    replace.set('replacementDocument', new Blob(['%PDF-1.4\n% Second revision\n%%EOF'], { type: 'application/pdf' }), 'revision.pdf');
    check((await request('/api/update_application_documents.php', staff, replace)).data.success, 'Stored document replacement succeeds');
    const edit = new URLSearchParams({ applicationId: 'CORRECT', lastName: 'Correction', firstName: 'Test', applicationType: 'senior', birthDate: '1940-01-01', contactNumber: '09170000000', completeAddress: 'Updated synthetic address' });
    check(!(await request('/api/update_application.php', other, edit)).data.success, 'Cross-barangay profile edit denied');
    check((await request('/api/update_application.php', staff, edit)).data.success, 'Correction profile edit succeeds');
    check((await action(staff, 'CORRECT', 'next')).data.current_status === 'For Review', 'Barangay resubmits corrected application');
    details = (await request('/api/get_application_details.php?id=CORRECT', staff)).data;
    check(!details.return_reason && details.history.some(h => h.new_state === 'Needs Correction'), 'Resubmission clears active reason and preserves history');
    check(!(await request('/api/update_application.php', staff, edit)).data.success, 'Profile editing locks after resubmission');
    check(!(await request('/api/update_application_documents.php', staff, upload)).data.success, 'Document editing locks after resubmission');
    check((await action(admin, 'CORRECT', 'next')).data.current_status === 'Verified', 'Department verifies resubmitted application');
    check(!(await action(admin, 'CORRECT', 'return', { comments: 'Reason', correctionDocuments: 'PSA' })).data.success, 'Completed application cannot be returned');
    check(!(await action(admin, 'ARCHIVED', 'next')).data.success, 'Archived application cannot advance');
    fixture('reconcile-restored-rejection');
    let reopened = (await request('/api/get_application_details.php?id=OTHERQUEUE', admin)).data;
    check(reopened.workflow_state === 'For Review' && reopened.status === 'pending',
        'Migration reopens previously restored rejected applications');
    check((await action(admin, 'OTHERQUEUE', 'reject', { comments: 'First rejection' })).data.current_status === 'Rejected',
        'Reopened application can be rejected and archived');
    check(!(await action(admin, 'OTHERQUEUE', 'next')).data.success, 'Rejected archived application cannot advance');
    const restore = await request('/api/restore_application.php', admin, new URLSearchParams({ id: 'OTHERQUEUE' }));
    check(restore.data.success && restore.data.message.includes('For Review'), 'Restore reopens a rejection for review');
    reopened = (await request('/api/get_application_details.php?id=OTHERQUEUE', admin)).data;
    check(reopened.workflow_state === 'For Review' && reopened.status === 'pending' && !reopened.is_archived
        && reopened.history.some(h => h.previous_state === 'Rejected' && h.new_state === 'For Review'),
        'Restore clears rejected status and records the reopening in history');
    check((await action(admin, 'OTHERQUEUE', 'reject', { comments: 'Second rejection' })).data.current_status === 'Rejected',
        'Restored application can be rejected and archived again');
    const archivedAgain = await request('/pages/department_archive.php?search=OTHERQUEUE&tab=applications', admin, undefined, true);
    check(archivedAgain.status === 200 && archivedAgain.data.includes('data-id="OTHERQUEUE"'),
        'Rejected application appears in the Department Archive');
    fixture('mark-active-rejected');
    const staleArchiveSearch = await request('/pages/department_archive.php?search=OTHERQUEUE&tab=applications', admin, undefined, true);
    check(staleArchiveSearch.data.includes('currently active with status') && staleArchiveSearch.data.includes('Reopen for review'),
        'Archive search locates a rejected record with an outdated active flag');
    check((await request('/api/restore_application.php', other, new URLSearchParams({ id: 'OTHERQUEUE' }))).status === 403,
        'Barangay staff cannot reopen an active rejected record');
    const reopenedStale = await request('/api/restore_application.php', admin, new URLSearchParams({ id: 'OTHERQUEUE' }));
    check(reopenedStale.data.success && (await request('/api/get_application_details.php?id=OTHERQUEUE', admin)).data.workflow_state === 'For Review',
        'Administrator can reopen an active rejected record for review');
    const activeArchiveSearch = await request('/pages/department_archive.php?search=OTHERQUEUE&tab=applications', admin, undefined, true);
    check(activeArchiveSearch.data.includes('currently active with status') && activeArchiveSearch.data.includes('Open current record'),
        'Archive search points to the current location of a restored application');
    fixture('audit-only-archive');
    const auditOnlySearch = await request('/pages/department_archive.php?search=PRX-MISSING&tab=applications', admin, undefined, true);
    check(auditOnlySearch.data.includes('No current application record found for PRX-MISSING')
        && auditOnlySearch.data.includes('View audit history'),
        'Archive search distinguishes an audit event from a current application record');
    check(!(await action(admin, 'UNDERAGE-PENSION', 'next')).data.success, 'Local Pension applicant under 65 cannot advance');
    check((await action(admin, 'PEN-LBANK', 'next')).data.current_status === 'Verified', 'Land Bank enrollment verifies for forwarding');
    const landbankTracker = await request('/pages/benefit_tracker.php?token=PRX-BENE&service=landbank', '', undefined, true);
    check(landbankTracker.data.includes('being forwarded to <strong>LANDBANK</strong>'), 'Verified Land Bank tracker shows forwarding notice');
    const legacyPenTracker = await request('/pages/benefit_tracker.php?token=PEN-LBANK', '', undefined, true);
    check(legacyPenTracker.data.includes('Enter a valid permanent PRX Token ID'), 'Public tracker rejects legacy PEN codes');
    check((await action(admin, 'CHANGE-REQUEST', 'next')).data.current_status === 'Verified', 'Information Change request verifies');
    const updatedSenior = await request('/api/get_application_details.php?id=PRX-BENE', admin);
    check(updatedSenior.data.contact_number === '09179999999' && updatedSenior.data.emergency_contact_name === 'Updated Contact'
        && updatedSenior.data.health_condition === 'Arthritis / Joint condition',
        'Verified Information Change updates the linked Senior ID profile');
    check((await request('/api/get_application_details.php?id=BURIAL30', admin)).data.burial_filing_days === 30, 'Exact 30-weekday filing boundary');
    check((await action(admin, 'BURIAL30', 'next')).data.success, 'Timely burial accepted even when reviewed months later');
    check(!(await action(admin, 'BURIAL31', 'next')).data.success, '31-weekday burial filing rejected');
    check(!(await action(admin, 'BURIALBAD', 'next')).data.success, 'Death date after filing rejected');
    check(!(await action(admin, 'BURIALMISSING', 'next')).data.success, 'Missing burial date rejected');
    const idPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==', 'base64');
    const governmentIdApplication = (idFiles) => {
        const data = new FormData();
        Object.entries({
            proxy_submit: '1', portal_option: 'new_senior', requestedBenefit: 'Senior Citizen ID Registration',
            idPurpose: 'new', healthStatus: 'Physically Fit', lastName: 'Upload', firstName: 'Pair',
            birthDate: '1940-01-01', contactNumber: '09170000123', placeOfBirth: 'Pasig City',
            gender: 'Female', civilStatus: 'Single', houseNo: '1', street: 'Synthetic Street',
            barangay: 'Bagong Ilog', confirmPrivacy: 'on', emergencyContactName: 'Test Contact',
            emergencyContact: '09170000456', emergencyContactRelationship: 'Child',
        }).forEach(([key, value]) => data.set(key, value));
        for (const field of ['psa_birth_cert_file', 'barangay_residency_file', 'id_photo_file']) {
            data.set(field, new Blob([idPng], { type: 'image/png' }), field + '.png');
        }
        idFiles.forEach(([name, mime, bytes]) => data.append('valid_id_file[]', new Blob([bytes], { type: mime }), name));
        return data;
    };
    const frontOnly = await request('/pages/proxy_registration.php', '', governmentIdApplication([['front.png', 'image/png', idPng]]), true);
    check(frontOnly.data.includes('Please upload both the front and back of your valid government ID.'), 'One government ID image is rejected during submission');
    const threeSides = await request('/pages/proxy_registration.php', '', governmentIdApplication([
        ['front.png', 'image/png', idPng], ['back.png', 'image/png', idPng], ['extra.png', 'image/png', idPng]
    ]), true);
    check(threeSides.data.includes('You can only upload 2 images: front and back of the ID.'), 'More than two government ID images are rejected');
    const pdfSide = await request('/pages/proxy_registration.php', '', governmentIdApplication([
        ['front.png', 'image/png', idPng], ['back.pdf', 'application/pdf', Buffer.from('%PDF-1.4\n%%EOF')]
    ]), true);
    check(pdfSide.data.includes('Upload PNG, JPG, or JPEG images only'), 'PDF government ID upload is rejected');
    const acceptedPair = await request('/pages/proxy_registration.php', '', governmentIdApplication([
        ['front.png', 'image/png', idPng], ['back.png', 'image/png', idPng]
    ]), true);
    const newPairId = acceptedPair.data.match(/PRX-[A-Z0-9]{4,12}/)?.[0];
    check(acceptedPair.data.includes('Pre-registration successful') && newPairId, 'A front-and-back image pair submits successfully');
    const storedPair = await request('/api/get_application_details.php?id=' + newPairId, admin);
    check(Boolean(storedPair.data.government_id_front) && Boolean(storedPair.data.government_id_back), 'Both uploaded sides are stored on the same application');
    check(storedPair.data.documents.some(document => document.document_key === 'government_id_front' && document.document_label.includes('Front of ID'))
        && storedPair.data.documents.some(document => document.document_key === 'government_id_back' && document.document_label.includes('Back of ID')),
        'Verification records label the two sides separately');
    for (const side of ['front', 'back']) {
        const file = await request('/api/get_document.php?id=' + newPairId + '&doc_type=government_id_' + side, admin, undefined, true);
        check(file.status === 200 && file.headers.get('content-type') === 'image/png', `Submitted ${side} image opens for verification`);
    }
    fixture('pension-ready');
    const pensionAccess = await request('/pages/senior_benefits.php', '', new URLSearchParams({
        verify_benefit_access: '1', seniorCitizenId: 'OSCA-TEST-BENEFITS', permanentToken: 'PRX-BENE'
    }), true);
    const pensionCookie = pensionAccess.headers.getSetCookie().map(cookie => cookie.split(';')[0]).join('; ');
    const pensionForm = new FormData();
    Object.entries({
        proxy_submit: '1', portal_option: 'verified_benefits', seniorCitizenId: 'OSCA-TEST-BENEFITS',
        permanentToken: 'PRX-BENE', requestedBenefit: 'Local Social Pension Assessment',
        atmCardNo: 'TEST-CARD', mothersMaidenName: 'Test Maiden', isPensioner: '0',
        isPermanentIncome: '0', familySupport: '0', healthCondition: 'Healthy',
        ownsHouse: '1', isRenter: '0', confirmPrivacy: 'on',
    }).forEach(([key, value]) => pensionForm.set(key, value));
    pensionForm.set('barangay_residency_file', new Blob([idPng], { type: 'image/png' }), 'residency.png');
    pensionForm.set('id_photo_file', new Blob([idPng], { type: 'image/png' }), 'portrait.png');
    pensionForm.append('psa_birth_cert_file[]', new Blob([idPng], { type: 'image/png' }), 'front.png');
    pensionForm.append('psa_birth_cert_file[]', new Blob([idPng], { type: 'image/png' }), 'back.png');
    const pensionPair = await request('/pages/senior_benefits.php', pensionCookie, pensionForm, true);
    check(pensionPair.data.includes('Benefit Application Submitted'), 'Senior Pension accepts two ID images without an optional pension record');
    const pensionId = fixture('latest-pension-id');
    const pensionDetails = await request('/api/get_application_details.php?id=' + pensionId, admin);
    check(pensionDetails.data.health_condition === 'Healthy',
        'Senior Pension stores the current condition submitted on its assessment form');
    check(Boolean(pensionDetails.data.government_id_front) && Boolean(pensionDetails.data.government_id_back),
        'Senior Pension stores front and back under one application');
    for (const [page, cookie] of [['department_dashboard.php', admin], ['barangay_dash.php', staff], ['department_records.php', admin], ['department_archive.php', admin], ['barangay_archive.php', staff], ['verify_document.php?application=UI-CORRECTION', admin], ['submit_application.php?application=UI-CORRECTION', staff], ['proxy_registration.php', ''], ['field_operations.php', admin], ['field_operations.php', staff], ['import_records.php', admin]]) {
        const html = (await request('/pages/' + page, cookie, undefined, true)).data;
        check(!/<b>(?:Fatal error|Warning)<\/b>/.test(html), 'PHP renders ' + page);
        for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
            if (/\bsrc=/.test(match[1]) || /application\/json/.test(match[1])) continue;
            new vm.Script(match[2], { filename: page });
        }
    }
    check(!/PHP (?:Fatal error|Warning)/.test(serverErrors), 'No PHP warnings or fatal errors during integration checks');
    console.log(`\n${passed} checks passed. Test server: ${base}`);
    if (process.argv.includes('--browser')) {
        for (const script of ['workflow.browser.cjs', 'modals.browser.cjs']) {
            await new Promise((resolve, reject) => {
                const child = spawn(process.execPath, [path.join(__dirname, script)], { cwd: root, env: process.env, windowsHide: true, stdio: 'inherit' });
                child.on('exit', code => code === 0 ? resolve() : reject(Error(script + ' failed')));
            });
        }
    }
    if (process.argv.includes('--serve')) { console.log('Synthetic browser fixtures: test-admin / test-staff; password TestOnly!2026.'); await new Promise(resolve => { process.on('SIGTERM', resolve); process.on('SIGINT', resolve); process.stdin.resume(); process.stdin.once('data', resolve); }); }
}
main().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (server && server.exitCode === null) { server.kill(); await new Promise(resolve => server.once('exit', resolve)); }
    fixture('cleanup');
    if (uploadedSystemLogo) fs.rmSync(path.join(root, 'images', 'system_logos', uploadedSystemLogo), { force: true });
    assert.equal(path.dirname(path.resolve(sessions)), path.resolve(os.tmpdir()));
    assert.ok(path.basename(sessions).startsWith('seniorlink-test-'));
    fs.rmSync(sessions, { recursive: true, force: true });
    assert.equal(path.dirname(path.resolve(privateStorage)), path.resolve(os.tmpdir()));
    assert.ok(path.basename(privateStorage).startsWith('seniorlink-private-test-'));
    fs.rmSync(privateStorage, { recursive: true, force: true });
});
