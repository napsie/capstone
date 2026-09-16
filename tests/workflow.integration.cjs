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
const fixture = mode => {
    const result = spawnSync(php, [path.join(__dirname, 'workflow_fixture.php'), mode, database], { cwd: root, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
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
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-private-proof.pdf'), '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF');
    fs.writeFileSync(path.join(privateStorage, 'uploads', 'synthetic-id-photo.png'), Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg==', 'base64'));
    fixture('private-upload');
    const deniedPrivateFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', '', undefined, true);
    check(deniedPrivateFile.status === 401, 'Anonymous private document access denied');
    const allowedPrivateFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', staff, undefined, true);
    check(allowedPrivateFile.status === 200 && allowedPrivateFile.data.startsWith('%PDF-1.4'), 'Authorized staff can view private document');
    const wrongBarangayFile = await request('/api/get_document.php?id=VALID&doc_type=proof_of_life', other, undefined, true);
    check(wrongBarangayFile.status === 404, 'Other barangay cannot view private document');
    const retiredPhotoRoute = await request('/api/digital_id_photo.php?token=PRX-BENE', '', undefined, true);
    check(retiredPhotoRoute.status === 410, 'Public token-only photo route is closed');
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
    for (const [page, cookie] of [['department_dashboard.php', admin], ['barangay_dash.php', staff], ['verify_document.php?application=UI-CORRECTION', admin], ['submit_application.php?application=UI-CORRECTION', staff], ['proxy_registration.php', ''], ['field_operations.php', admin], ['field_operations.php', staff], ['import_records.php', admin]]) {
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
    assert.equal(path.dirname(path.resolve(sessions)), path.resolve(os.tmpdir()));
    assert.ok(path.basename(sessions).startsWith('seniorlink-test-'));
    fs.rmSync(sessions, { recursive: true, force: true });
    assert.equal(path.dirname(path.resolve(privateStorage)), path.resolve(os.tmpdir()));
    assert.ok(path.basename(privateStorage).startsWith('seniorlink-private-test-'));
    fs.rmSync(privateStorage, { recursive: true, force: true });
});
