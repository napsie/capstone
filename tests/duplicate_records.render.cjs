const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {}, document: { readyState: 'loading', addEventListener() {} } };
vm.createContext(context);
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/application-details.js'), 'utf8'), context);
const app = { application_type: 'senior', full_name: 'Current applicant', duplicate_review: {
    count: 1, scope: 'Citywide', matches: [{ percentage: 80, score: 80, full_name: '<script>alert(1)</script>',
        id_number: 'MATCH', birth_date: '1950-01-01', barangay: 'Sample', matched_fields: ['Name (+45)', 'Birth date (+35)'] }]
} };
for (const method of ['renderApplicationEditContext', 'renderApplicationVerificationDetails', 'renderApplicationRecordDetails']) {
    const html = context.window[method](app);
    assert.ok(html.includes('80% match indicator (80 points)'));
    assert.ok(html.includes('manual review required'));
    assert.ok(html.includes('&lt;script&gt;'));
    assert.ok(!html.includes('<script>'));
    assert.ok(!context.window[method]({ ...app, duplicate_review: { count: 0, matches: [] } }).includes('manual review required'));
    assert.ok(context.window[method]({ ...app, duplicate_review: { unavailable: true } }).includes('temporarily unavailable'));
}
console.log('PASS duplicate warning in edit, verification, and record views; escaping, no-match, and unavailable states');
