// Run: NODE_PATH=<bundled node_modules> node tests/mobile_submission.browser.cjs
// Uses a real mobile-sized browser and deterministic network failures.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../assets/js/resilient-form-submit.js'), 'utf8');
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 390, height: 740 }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    try {
        await page.route('http://example.test/submit', route => route.fulfill({ status: 200, body: '<html><body>Ready</body></html>' }));
        await page.goto('http://example.test/submit');
        await page.setContent('<form id="mainAppForm" action="http://example.test/submit" method="post" enctype="multipart/form-data" novalidate><label for="seniorName">Name</label><input id="seniorName" name="name" required><label for="proof">Proof</label><input id="proof" name="proof" type="file"><button type="submit">Submit</button></form>');
        await page.addScriptTag({ content: script });
        await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
        await page.locator('button[type=submit]').click();
        assert.match(await page.locator('[data-submission-error]').innerText(), /fill out|name|required/i);
        console.log('PASS Missing field has adjacent error.');

        await page.locator('#seniorName').fill('Maria Test');
        let calls = 0;
        await page.unroute('http://example.test/submit');
        await page.route('http://example.test/submit', async route => {
            calls++;
            await wait(600);
            await route.fulfill({ status: 503, body: 'Temporary failure' });
        });
        await page.locator('button[type=submit]').click();
        await page.locator('button[type=submit]').evaluate(button => button.click());
        await page.locator('[data-submission-error]').filter({ hasText: 'HTTP 503' }).waitFor();
        assert.equal(calls, 1);
        assert.equal(await page.locator('#seniorName').inputValue(), 'Maria Test');
        console.log('PASS Slow connection and repeated taps send once and preserve values.');

        await page.locator('#proof').setInputFiles({ name: 'proof.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('test') });
        await page.unroute('http://example.test/submit');
        await page.route('http://example.test/submit', route => route.abort('failed'));
        await page.locator('button[type=submit]').click();
        await page.locator('[data-submission-error]').filter({ hasText: 'upload was interrupted' }).waitFor();
        assert.equal(await page.locator('#proof').evaluate(input => input.files.length), 1);
        assert.equal(await page.locator('#seniorName').inputValue(), 'Maria Test');
        console.log('PASS Interrupted upload shows file error and retains inputs.');

        await page.unroute('http://example.test/submit');
        await page.route('http://example.test/submit', route => route.fulfill({ status: 200, body: '<html><body>Sign in</body></html>' }));
        await page.locator('button[type=submit]').click();
        await page.locator('[data-submission-error]').filter({ hasText: 'session expired' }).waitFor();
        assert.equal(await page.locator('#seniorName').inputValue(), 'Maria Test');
        console.log('PASS Expired session is explicit and keeps the form.');

        await page.locator('#seniorName').focus();
        await page.setViewportSize({ width: 390, height: 430 });
        await page.setViewportSize({ width: 390, height: 740 });
        assert.equal(await page.locator('#seniorName').inputValue(), 'Maria Test');
        assert.equal(await page.locator('button[type=submit]').isVisible(), true);
        console.log('PASS Closing a mobile keyboard does not lose values or the submit button.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
