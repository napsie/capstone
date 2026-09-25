const { chromium } = require('playwright');
const assert = require('node:assert/strict');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto('http://127.0.0.1:8765/index.php?view=admin');
        await page.locator('#adminUsername').fill('remember-this-value');
        assert.equal((await page.locator('label[for="adminUsername"]').innerText()).trim(), 'Username / Pangalan ng Gumagamit');
        assert.equal(await page.locator('#adminUsername').inputValue(), 'remember-this-value');

        await page.goto('http://127.0.0.1:8765/pages/proxy_registration.php');
        assert.equal((await page.locator('label[for="lastName"]').innerText()).replace('*', '').trim(), 'Last Name / Apelyido');
        await page.locator('#lastName').fill('Dela Cruz');
        assert.equal(await page.locator('#lastName').inputValue(), 'Dela Cruz');
        assert.deepEqual(errors, []);
        console.log('PASS Forms show English/Tagalog labels and preserve entered values.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exit(1);
});
