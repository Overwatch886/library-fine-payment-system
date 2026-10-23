const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');

function fixture(action, ...args) {
    const output = execFileSync(process.env.LIBRARY_TEST_PHP,
        [path.join(__dirname, '../support/browser-fixture.php'), action, ...args],
        { encoding: 'utf8' });
    return JSON.parse(output);
}

let today;
test.beforeEach(async ({ page }) => {
    today = fixture('reset').today;
    page.on('dialog', (dialog) => dialog.accept());
});

async function login(page, matric = '214567', prefix = '') {
    await page.goto(`${prefix}/login.php`);
    await page.locator('#username').fill(matric);
    await page.locator('#password').fill('Password123');
    await page.getByRole('button', { name: 'Log In', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`${prefix}/catalogue\\.php$`));
    await expect(page.locator('.book-card').first()).toBeVisible();
}

async function openBorrow(page) {
    const card = page.locator('.book-card').filter({ has: page.getByRole('heading', { name: 'Clean Code', exact: true }) });
    await card.getByRole('button', { name: 'Borrow', exact: true }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    return page.getByLabel('Return date', { exact: true });
}

async function saveScreenshot(page, filename, fullPage = true) {
    const directory = path.join(__dirname, '../../docs/screenshots');
    fs.mkdirSync(directory, { recursive: true });
    await page.screenshot({ path: path.join(directory, filename), fullPage });
}

for (const prefix of ['', '/library-demo']) {
    test(`login flash, nested history assets and navigation (${prefix || 'root'})`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(`${prefix}/loans/history.php`);
        await expect(page).toHaveURL(new RegExp(`${prefix}/login\\.php$`));
        await expect(page.locator('#login-flash')).toContainText('Please log in');
        await page.reload();
        await expect(page.locator('#login-flash')).toHaveCount(0);
        await login(page, '221345', prefix);
        await page.getByRole('link', { name: 'Borrowing History', exact: true }).click();
        await expect(page.locator('#history-tbody tr')).toHaveCount(2);
        await expect(page.locator('#history-table')).toBeVisible();
        expect(await page.locator('.table-scroll').evaluate((element) => getComputedStyle(element).overflowX)).toBe('auto');
        await page.getByRole('link', { name: 'Catalogue', exact: true }).click();
        await expect(page.locator('.book-card').first()).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('incorrect classic login stays on page with useful error', async ({ page }) => {
    await page.goto('/login.php');
    await page.locator('#username').fill('214567');
    await page.locator('#password').fill('incorrect');
    await page.getByRole('button', { name: 'Log In', exact: true }).click();
    await expect(page.locator('#login-error')).toHaveText('Incorrect username or password.');
});

test('date defaults, calendar edges, keyboard cancellation and focus', async ({ page }) => {
    await login(page);
    const date = await openBorrow(page);
    const expected = await page.evaluate((day) => ({ min: addCalendarDays(day, 1), max: addCalendarDays(day, 14) }), today);
    await expect(date).toHaveAttribute('min', expected.min);
    await expect(date).toHaveAttribute('max', expected.max);
    await expect(date).toHaveValue(expected.max);
    await expect(date).toBeFocused();
    await page.locator('#modal-confirm').focus();
    await page.keyboard.press('Tab');
    await expect(date).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(page.locator('#modal-confirm')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(page.locator('.book-card').filter({ hasText: 'Clean Code' }).getByRole('button')).toBeFocused();
    const dates = await page.evaluate(() => [
        addCalendarDays('2028-02-28', 1), addCalendarDays('2027-02-28', 1),
        addCalendarDays('2026-12-31', 1), addCalendarDays('2026-01-31', 1),
        libraryToday(new Date('2026-12-31T23:30:00Z')),
    ]);
    expect(dates).toEqual(['2028-02-29', '2027-03-01', '2027-01-01', '2026-02-01', '2027-01-01']);
});

test('chosen date reaches API, saved loan, history and return', async ({ page }) => {
    await login(page);
    const date = await openBorrow(page);
    const chosen = await date.getAttribute('min');
    await date.fill(chosen);
    const request = page.waitForRequest((req) => req.url().endsWith('/api/loans/borrow.php'));
    await page.locator('#modal-confirm').click();
    expect((await request).postDataJSON()).toEqual({ book_id: 2, due_date: chosen });
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(fixture('loan', '214567', '2')).toEqual({ due_date: chosen, status: 'borrowed' });
    await page.getByRole('link', { name: 'Borrowing History', exact: true }).click();
    await expect(page.locator('#history-tbody tr').filter({ hasText: 'Clean Code' })).toContainText('Borrowed');
    await page.getByRole('link', { name: 'My Loans', exact: true }).click();
    await page.locator('#loans-container tr').filter({ hasText: 'Clean Code' }).getByRole('button', { name: 'Return' }).click();
    await expect(page.locator('#loans-container')).toContainText('You have no active loans.');
    expect(fixture('loan', '214567', '2').status).toBe('returned');
    await page.getByRole('link', { name: 'Borrowing History', exact: true }).click();
    await expect(page.locator('#history-tbody tr').filter({ hasText: 'Clean Code' })).toContainText('Returned');
});

test('browser rejects date outside permitted range without an API request', async ({ page }) => {
    await login(page);
    const date = await openBorrow(page);
    let requests = 0;
    page.on('request', (request) => { if (request.url().endsWith('/api/loans/borrow.php')) requests++; });
    await date.fill(today);
    await page.locator('#modal-confirm').click();
    expect(await date.evaluate((element) => element.validity.rangeUnderflow)).toBe(true);
    expect(requests).toBe(0);
    await expect(page.getByRole('dialog')).toBeVisible();
});

test('backend rejection preserves selection and network error permits retry', async ({ page }) => {
    await login(page, '220981');
    const date = await openBorrow(page);
    const chosen = await date.getAttribute('min');
    await date.fill(chosen);
    await page.locator('#modal-confirm').click();
    await expect(page.locator('#borrow-error')).toContainText(/unpaid fine/i);
    await expect(date).toHaveValue(chosen);
    await page.route('**/api/loans/borrow.php', (route) => route.abort());
    await page.locator('#modal-confirm').click();
    await expect(page.locator('#borrow-error')).toHaveText('Something went wrong. Please try again.');
    await expect(page.locator('#modal-confirm')).toBeEnabled();
    await expect(date).toHaveValue(chosen);
});

test('pending borrow disables controls and submits only once', async ({ page }) => {
    await login(page);
    await openBorrow(page);
    let release;
    const gate = new Promise((resolve) => { release = resolve; });
    let requests = 0;
    await page.route('**/api/loans/borrow.php', async (route) => { requests++; await gate; await route.continue(); });
    await page.locator('#modal-confirm').click();
    await expect(page.locator('#modal-confirm')).toBeDisabled();
    await expect(page.locator('#modal-cancel')).toBeDisabled();
    await page.locator('#borrow-form').evaluate((form) => form.dispatchEvent(new Event('submit', { cancelable: true })));
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeVisible();
    expect(requests).toBe(1);
    release();
    await expect(page.getByRole('dialog')).toBeHidden();
});

test('history loading, empty, API error, network error and expired session', async ({ page }) => {
    await login(page, '299999');
    let release;
    const gate = new Promise((resolve) => { release = resolve; });
    await page.route('**/api/loans/history.php', async (route) => { await gate; await route.continue(); });
    await page.goto('/loans/history.php');
    await expect(page.locator('#history-placeholder')).toContainText('Loading');
    release();
    await expect(page.locator('#history-placeholder')).toHaveText('No loans in your history yet.');
    await page.unroute('**/api/loans/history.php');
    await page.route('**/api/loans/history.php', (route) => route.fulfill({ json: { success: false, message: 'History unavailable.' } }));
    await page.reload();
    await expect(page.locator('#history-placeholder')).toHaveText('History unavailable.');
    await page.unroute('**/api/loans/history.php');
    await page.route('**/api/loans/history.php', (route) => route.abort());
    await page.reload();
    await expect(page.locator('#history-placeholder')).toHaveText('Something went wrong. Please try again.');
    await page.unroute('**/api/loans/history.php');
    await page.route('**/api/loans/history.php', (route) => route.fulfill({ status: 401, json: { success: false } }));
    await page.reload();
    await expect(page).toHaveURL(/\/login\.php$/);
});

test('late return produces saved fine and history, including cap', async ({ page }) => {
    await login(page, '232118');
    await page.goto('/my_loans.php');
    await expect(page.locator('#accruing-container .fine-item')).toHaveCount(2);
    await page.locator('#loans-container tr').filter({ hasText: 'Macroeconomics' }).getByRole('button', { name: 'Return' }).click();
    await expect(page.locator('#fines-container')).toContainText('₦5,000.00');
    await page.goto('/loans/history.php');
    const row = page.locator('#history-tbody tr').filter({ hasText: 'Macroeconomics' });
    await expect(row).toContainText('Returned');
    await expect(row).toContainText('₦5,000.00 (Unpaid)');
    await saveScreenshot(page, 'history.png');
});

test('quoted HTML-like titles remain literal in history, payment modal and receipt', async ({ page }) => {
    const title = 'A "quoted" <img src=x onerror="window.titleInjected=1"> title';
    fixture('quoted-title');
    await login(page, '220981');
    await page.goto('/loans/history.php');
    await expect(page.locator('#history-tbody td').first()).toHaveText(title);
    expect(await page.locator('#history-tbody img').count()).toBe(0);
    await page.goto('/my_loans.php');
    await page.locator('.pay-btn').click();
    await expect(page.locator('#pay-book-title')).toHaveText(title);
    await page.locator('#pay-channel').selectOption('ussd');
    await page.locator('#pay-confirm').click();
    await page.locator('#payment-result').getByRole('link', { name: 'View Receipt' }).click();
    await expect(page.locator('.receipt-details')).toContainText(title);
    expect(await page.locator('.receipt-details img').count()).toBe(0);
    expect(await page.evaluate(() => window.titleInjected)).toBeUndefined();
});

for (const prefix of ['', '/library-demo']) {
    test(`decline then success, persistent receipt and print (${prefix || 'root'})`, async ({ page }) => {
        await login(page, '220981', prefix);
        await page.goto(`${prefix}/my_loans.php`);
        await page.locator('.pay-btn').click();
        await page.locator('#pay-card-number').fill('4000000000000000');
        await page.locator('#pay-confirm').click();
        await expect(page.locator('#pay-card-number')).toHaveValue('');
        await page.locator('#payment-result').getByRole('link', { name: 'View Failed Attempt' }).click();
        await expect(page.locator('.receipt-notice')).toContainText('did not pay the fine');
        await expect(page.locator('.receipt-details')).toContainText('Failed');
        await saveScreenshot(page, 'failed-payment.png');
        await page.getByRole('link', { name: 'My Loans', exact: true }).click();
        await page.locator('.pay-btn').click();
        await page.locator('#pay-card-number').fill('4000123456781111');
        await page.locator('#pay-confirm').click();
        await expect(page.locator('#fines-container .pay-btn')).toHaveCount(0);
        await page.reload();
        await page.locator('#fines-container').getByRole('link', { name: 'View Receipt' }).click();
        await expect(page.locator('.receipt-notice')).toContainText(/successful/i);
        await expect(page.locator('.receipt-details')).toContainText('Card');
        await expect(page.locator('.receipt-details')).toContainText('₦700.00');
        await expect(page.locator('#print-receipt')).toBeVisible();
        await saveScreenshot(page, 'successful-receipt.png');
        await page.evaluate(() => { window.print = () => { window.printClicked = true; }; });
        await page.locator('#print-receipt').click();
        expect(await page.evaluate(() => window.printClicked)).toBe(true);
        await page.emulateMedia({ media: 'print' });
        await expect(page.locator('.page-header')).toBeHidden();
        await expect(page.locator('.page-footer')).toBeHidden();
        await expect(page.locator('#print-receipt')).toBeHidden();
        await expect(page.locator('.receipt-details')).toBeVisible();
    });
}

for (const channel of ['bank_transfer', 'ussd']) {
    test(`payment through ${channel} hides card input and succeeds`, async ({ page }) => {
        await login(page, '220981');
        await page.goto('/my_loans.php');
        await page.locator('.pay-btn').click();
        await page.locator('#pay-channel').selectOption(channel);
        await expect(page.locator('#card-number-wrap')).toBeHidden();
        await page.locator('#pay-confirm').click();
        await expect(page.locator('#payment-result')).toContainText('View Receipt');
        await expect(page.locator('#fines-container')).toContainText('Paid');
    });
}

test('receipt missing/not-found/network errors never display a printable receipt', async ({ page }) => {
    await login(page);
    await page.goto('/payments/receipt.php');
    await expect(page.locator('#receipt-container')).toContainText(/reference/i);
    await expect(page.locator('#print-receipt')).toBeHidden();
    await page.goto('/payments/receipt.php?reference=missing');
    await expect(page.locator('#receipt-container')).toContainText(/not found/i);
    await expect(page.locator('#print-receipt')).toBeHidden();
    await page.route('**/api/payments/receipt.php?*', (route) => route.abort());
    await page.reload();
    await expect(page.locator('#receipt-container')).toContainText('Something went wrong');
    await expect(page.locator('#print-receipt')).toBeHidden();
});

test('another student receipt stays private while its owner can reopen it', async ({ page, browser }) => {
    await login(page, '205643');
    await page.goto('/my_loans.php');
    await page.locator('#fines-container').getByRole('link', { name: 'View Receipt' }).click();
    await expect(page.locator('.receipt-details')).toContainText('₦500.00');
    const receiptURL = page.url();
    const otherContext = await browser.newContext();
    try {
        const other = await otherContext.newPage();
        await login(other);
        await other.goto(receiptURL);
        await expect(other.locator('#receipt-container')).toContainText(/not found/i);
        await expect(other.locator('#print-receipt')).toBeHidden();
    } finally {
        await otherContext.close();
    }
});

test('mobile catalogue, modal, history and receipt stay within viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, '205643');
    await openBorrow(page);
    await saveScreenshot(page, 'due-date-mobile.png', false);
    await page.keyboard.press('Escape');
    for (const route of ['/loans/history.php', '/my_loans.php']) {
        await page.goto(route);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.locator('#fines-container').getByRole('link', { name: 'View Receipt' }).click();
    await expect(page.locator('.receipt-details')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});
