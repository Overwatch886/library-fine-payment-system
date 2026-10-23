const { defineConfig } = require('@playwright/test');

const url = process.env.LIBRARY_TEST_URL;
if (!url || !/^http:\/\/127\.0\.0\.1:\d+$/.test(url)) {
    throw new Error('Run browser tests through php tests/run.php browser, not against the app database.');
}

module.exports = defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: 'list',
    use: {
        baseURL: url,
        browserName: 'chromium',
        viewport: { width: 1280, height: 900 },
        timezoneId: 'Africa/Lagos',
        // Payment requests contain test card digits: do not save traces or videos.
        trace: 'off',
        video: 'off',
        screenshot: 'off',
    },
});
