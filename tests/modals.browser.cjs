// Run by workflow.integration.cjs --browser against its synthetic database.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8765';
let browser;
(async () => {
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    let checks = 0;
    const errors = [];
    const contexts = {};
    for (const role of ['admin', 'staff']) {
        const context = await browser.newContext({ viewport: { width: 1365, height: 1000 } });
        contexts[role] = context;
        context.setDefaultTimeout(10000);
        await context.request.post(base + '/index.php', {
            form: role === 'admin'
                ? { login_role: 'department_admin', username: 'test-admin', password: 'TestOnly!2026' }
                : { login_role: 'barangay_staff', username: 'test-staff', password: 'TestOnly!2026', barangay: 'Bagong Ilog' }
        });
    }
    const routes = [
        ['verify_document.php', 'admin'], ['submit_application.php', 'staff'],
        ['department_records.php', 'admin'], ['barangay_records.php', 'staff'],
        ['department_archive.php', 'admin'], ['barangay_archive.php', 'staff'],
    ];
    const check = (value, label) => { assert.ok(value, label); checks++; };
    for (const [file, role] of routes) {
        const page = await contexts[role].newPage();
        page.on('pageerror', error => errors.push(file + ': ' + error.message));
        await page.goto(base + '/pages/' + file);
        const archive = file.includes('archive');
        const modalId = archive ? 'archiveApplicationModal' : 'applicationModal';
        const titleId = archive ? 'archiveApplicationModalTitle' : 'modalAppTitle';
        const modal = page.locator('#' + modalId);
        const open = async id => {
            if (archive) await page.locator(`.archive-application-row[data-id="${id}"]`).dispatchEvent('click');
            else await page.evaluate(id => window.openApplicationModal(id), id);
        };
        const loaded = async name => {
            await page.locator('#' + titleId).filter({ hasText: name }).waitFor();
            await page.waitForFunction(id => document.getElementById(id).getAttribute('aria-busy') === 'false', modalId);
            check(!await modal.evaluate(el => el.classList.contains('application-data-pending')), file + ' rendered content');
        };
        const close = async () => {
            await modal.locator('.modal-close, #closeArchiveApplicationModal').first().click();
        };
        if (archive) {
            await open('ARCHIVED');
            await loaded('Archived Senior');
            check((await modal.innerText()).includes('Synthetic test address'), file + ' complete applicant information');
            check((await modal.innerText()).includes('Supporting document for ARCHIVED'), file + ' documents');
            check((await modal.innerText()).includes('Synthetic history for ARCHIVED'), file + ' history');
        } else {
            // First exercise the actual table-row event binding.
            const rowId = file.includes('records') ? 'FORM-senior' : 'UI-CORRECTION';
            await page.locator(`${file.includes('records') ? '.record-row' : '.applicant-row'}[data-id="${rowId}"]`).dispatchEvent('click');
            await loaded(rowId === 'FORM-senior' ? 'Sample senior Applicant' : 'Sample Correction Request');
            await close();
            for (const type of ['senior', 'pension', 'national_pension', 'landbank', 'milestone_gift', 'burial', 'home_visit']) {
                await open('FORM-' + type);
                await loaded('Sample ' + type + ' Applicant');
                check((await modal.innerText()).includes('Supporting document for FORM-' + type), file + ' ' + type + ' documents');
                check((await modal.innerText()).includes('Synthetic history for FORM-' + type), file + ' ' + type + ' history');
                check(!(await modal.innerText()).includes('undefined'), file + ' ' + type + ' no undefined values');
                await close();
            }
            await open('FORM-senior');
            await loaded('Sample senior Applicant');
        }
        // Fail after a successfully viewed record: old details must be hidden.
        const retryId = archive ? 'ARCHIVED' : 'FORM-senior';
        let fail = true;
        await page.route('**/api/get_application_details.php*', route => fail
            ? route.fulfill({ status: 500, contentType: 'text/html', body: '<b>temporary failure</b>' })
            : route.continue());
        await open(retryId);
        await modal.locator('.application-data-status[role=alert]').waitFor();
        check(await modal.evaluate(el => el.classList.contains('application-data-pending')), file + ' hides stale content on failure');
        fail = false;
        await modal.getByRole('button', { name: 'Try again', exact: true }).click();
        await loaded(archive ? 'Archived Senior' : 'Sample senior Applicant');
        await page.unroute('**/api/get_application_details.php*');
        // Delay A, switch to B, and verify A cannot overwrite B.
        const firstId = archive ? 'ARCHIVED' : 'FORM-senior';
        const secondId = archive ? 'ARCHIVED-2' : 'FORM-pension';
        await page.route('**/api/get_application_details.php*', async route => {
            if (new URL(route.request().url()).searchParams.get('id') === firstId) {
                await new Promise(resolve => setTimeout(resolve, 250));
            }
            await route.continue().catch(() => {});
        });
        await open(firstId);
        await close();
        await open(secondId);
        await loaded(archive ? 'Second Archived Senior' : 'Sample pension Applicant');
        await page.waitForTimeout(350);
        check((await page.locator('#' + titleId).innerText()).includes(archive ? 'Second Archived Senior' : 'Sample pension Applicant'), file + ' latest request wins');
        await page.unroute('**/api/get_application_details.php*');
        if (!archive) {
            await open('NONEXISTENT');
            await modal.locator('.application-data-status').filter({ hasText: 'Application not found or access denied.' }).waitFor();
            check(true, file + ' missing record handled');
            if (role === 'staff') {
                await open('OTHER');
                await modal.locator('.application-data-status').filter({ hasText: 'Application not found or access denied.' }).waitFor();
                check(true, file + ' cross-barangay data blocked');
            }
        }
        console.log('PASS ' + file + ': information, documents, history, retry, and record switching');
        await page.close();
    }
    const users = await contexts.admin.newPage();
    users.on('pageerror', error => errors.push(error.message));
    await users.goto(base + '/pages/user_management.php');
    await users.locator('.edit-user-btn[data-id="2"]').click();
    await users.locator('#editUserModal').waitFor();
    check(await users.locator('#editUsername').inputValue() === 'test-staff', 'User modal loads selected user');
    check(await users.locator('#editNewPassword').inputValue() === '', 'User modal never populates stored password');
    const userData = await (await contexts.admin.request.get(base + '/pages/edit_user.php?id=2&modal=true')).json();
    check(!('password' in userData.user), 'User details response excludes password hash');
    console.log('PASS user_management.php: selected user loads correctly and password stays private');
    for (const role of ['admin', 'staff']) {
        const operations = await contexts[role].newPage();
        operations.on('pageerror', error => errors.push(error.message));
        await operations.goto(base + '/pages/field_operations.php');
        check(await operations.locator('#tab-visits').isVisible(), role + ' home visits remain available');
        if (role === 'admin') {
            await operations.locator('#personnelTab').click();
            check(await operations.locator('#tab-personnel').isVisible(), 'Personnel tab remains functional');
        }
        await operations.close();
    }
    console.log('PASS Field Operations: home visits and personnel tabs work after obsolete module removal');
    assert.deepEqual(errors, [], 'No JavaScript errors in information modals');
    console.log(`${checks} modal and field-operations checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => { if (browser) await browser.close(); });
