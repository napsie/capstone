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
const database = 'seniorlink_test_' + randomBytes(6).toString('hex');
const base = 'http://127.0.0.1:8765';
const sessions = fs.mkdtempSync(path.join(os.tmpdir(), 'seniorlink-test-'));
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
    const result = await request('/index.php', '', new URLSearchParams({ login_role: department ? 'department_admin' : 'barangay_staff', username: name, password: 'TestOnly!2026', barangay }), true);
    check(result.status === 302, 'Login ' + name);
    return result.headers.getSetCookie().map(cookie => cookie.split(';')[0]).join('; ');
}
const action = (cookie, id, action, fields = {}) => request('/api/update_workflow_status.php', cookie, new URLSearchParams({ applicationId: id, action, ...fields }));
async function main() {
    fixture('setup');
    server = spawn(php, ['-d', 'session.save_path=' + sessions, '-d', 'upload_tmp_dir=' + sessions, '-S', '127.0.0.1:8765', '-t', root], {
        cwd: root, env: { ...process.env, DATABASE_URL: `mysql://root@localhost/${database}` }, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'],
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
    check((await request('/api/get_application_details.php?id=BURIAL30', admin)).data.burial_filing_days === 30, 'Exact 30-weekday filing boundary');
    check((await action(admin, 'BURIAL30', 'next')).data.success, 'Timely burial accepted even when reviewed months later');
    check(!(await action(admin, 'BURIAL31', 'next')).data.success, '31-weekday burial filing rejected');
    check(!(await action(admin, 'BURIALBAD', 'next')).data.success, 'Death date after filing rejected');
    check(!(await action(admin, 'BURIALMISSING', 'next')).data.success, 'Missing burial date rejected');
    for (const [page, cookie] of [['department_dashboard.php', admin], ['barangay_dash.php', staff], ['verify_document.php?application=UI-CORRECTION', admin], ['submit_application.php?application=UI-CORRECTION', staff], ['new_application.php', staff], ['proxy_registration.php', ''], ['field_operations.php', admin], ['field_operations.php', staff]]) {
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
});
