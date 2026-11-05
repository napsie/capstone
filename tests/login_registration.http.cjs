// Browser and HTTP checks use an isolated synthetic database.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn, spawnSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const php = 'D:/xampp1/php/php.exe';
const nodeModules = 'C:/Users/PLPASIG/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules';
const database = 'seniorlink_test_' + randomBytes(6).toString('hex');
const sessions = fs.mkdtempSync(path.join(os.tmpdir(), 'seniorlink-registration-'));
const password = 'TestOnlyMaster!2026';
const hash = spawnSync(php, ['-r', `echo password_hash('${password}', PASSWORD_DEFAULT);`], { encoding: 'utf8', windowsHide: true });
if (hash.status !== 0) throw Error(hash.stderr);
let server;
let browser;
let passed = 0;
function check(condition, label) { assert.ok(condition, label); passed++; console.log('PASS ' + label); }
function fixture(mode) {
    const result = spawnSync(php, [path.join(__dirname, 'workflow_fixture.php'), mode, database], { cwd: root, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
}

(async () => {
    try {
        fixture('setup');
        const port = await new Promise(resolve => {
            const socket = net.createServer();
            socket.listen(0, '127.0.0.1', () => {
                const selected = socket.address().port;
                socket.close(() => resolve(selected));
            });
        });
        const base = `http://127.0.0.1:${port}`;
        let errors = '';
        server = spawn(php, ['-d', 'session.save_path=' + sessions, '-S', '127.0.0.1:' + port, '-t', root], {
            cwd: root,
            env: { ...process.env, DATABASE_URL: 'mysql://root@localhost/' + database, MASTER_PASSWORD_HASH: hash.stdout.trim() },
            windowsHide: true,
            stdio: ['ignore', 'ignore', 'pipe'],
        });
        server.stderr.on('data', chunk => { errors += chunk; });
        const { chromium } = require(nodeModules + '/playwright');
        browser = await chromium.launch({ channel: 'chrome', headless: true });
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const page = await context.newPage();
        for (let attempt = 0; attempt < 40; attempt++) {
            try { await page.goto(base + '/index.php?view=admin'); break; }
            catch { await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        const signupButton = page.getByRole('button', { name: 'Create staff account' });
        check(await signupButton.isVisible(), 'OSCA login shows Create staff account');
        await signupButton.click();
        const form = page.frameLocator('#signupFrame');
        check(await form.locator('#firstName').isVisible(), 'Registration form opens from login');
        check(await form.locator('#role option').count() === 2, 'Form offers SHDO and department roles');
        await page.setViewportSize({ width: 390, height: 844 });
        check(await page.locator('#signupFrame').evaluate(frame => { const box = frame.getBoundingClientRect(); return box.width > 250 && box.left >= -1 && box.right <= innerWidth + 1; }), 'Registration form fits a phone screen');
        await page.setViewportSize({ width: 1440, height: 900 });
        await form.locator('#firstName').fill('New');
        await form.locator('#lastName').fill('Staff');
        await form.locator('#email').fill('new-public-staff@example.invalid');
        await form.locator('#username').fill('test-public-signup');
        await form.locator('#password').fill('TestOnly!2026');
        await form.locator('#confirmPassword').fill('TestOnly!2026');
        await form.locator('#barangay').selectOption('Bambang');
        await form.locator('#phone').fill('09170009999');
        await form.locator('#masterPassword').fill('WrongPassword!2026');
        await form.getByRole('button', { name: 'Create account' }).click();
        const invalidPassword = form.getByText('Invalid Master Password. Please try again.');
        try { await invalidPassword.waitFor({ state: 'visible', timeout: 5000 }); }
        catch { console.log('Registration form state:', (await form.locator('body').innerText()).slice(0, 1600)); }
        check(await invalidPassword.isVisible(), 'Wrong authorization password is rejected');
        await form.locator('#firstName').fill('New');
        await form.locator('#lastName').fill('Staff');
        await form.locator('#email').fill('new-public-staff@example.invalid');
        await form.locator('#username').fill('test-public-signup');
        await form.locator('#password').fill('TestOnly!2026');
        await form.locator('#confirmPassword').fill('TestOnly!2026');
        await form.locator('#barangay').selectOption('Bambang');
        await form.locator('#phone').fill('09170009999');
        await form.locator('#masterPassword').fill(password);
        await form.getByRole('button', { name: 'Create account' }).click();
        const success = form.getByText('User registered successfully!', { exact: false });
        try { await success.waitFor({ state: 'visible', timeout: 5000 }); }
        catch { console.log('Registration form state:', (await form.locator('body').innerText()).slice(0, 1600)); }
        check(await success.isVisible(), 'Authorized login-page registration succeeds');
        await page.goto(base + '/index.php?view=staff');
        await page.locator('#staffUsername').fill('test-public-signup');
        await page.locator('#staffPassword').fill('TestOnly!2026');
        await page.locator('#staffBarangay').selectOption('Bambang');
        await page.getByRole('button', { name: 'Sign in to SHDO' }).click();
        check(page.url().endsWith('/pages/barangay_dash.php'), 'New SHDO account can sign in');
        check((await context.request.get(base + '/pages/signup.php')).status() === 403, 'Signed-in SHDO cannot create another account');
        check(!/PHP (Fatal error|Warning|Parse error)/.test(errors), 'Registration requests produce no PHP errors');
        await context.close();
        console.log(`${passed} login registration checks passed.`);
    } finally {
        if (browser) await browser.close();
        if (server && server.exitCode === null) {
            const stopped = new Promise(resolve => server.once('exit', resolve));
            server.kill();
            await stopped;
        }
        fixture('cleanup');
        fs.rmSync(sessions, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
