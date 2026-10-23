<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/support/TestRunner.php';
require_once __DIR__ . '/support/TestEnvironment.php';
require_once __DIR__ . '/support/HttpClient.php';

$mode = $argv[1] ?? 'integration';
if (!in_array($mode, ['integration', 'browser'], true)) {
    fwrite(STDERR, "Use: php tests/run.php integration|browser\n");
    exit(1);
}
$environment = null;
$exitCode = 1;
try {
    $environment = new TestEnvironment();
    print "Separate test database and loopback server ready. App data is not used.\n";
    if ($mode === 'integration') {
        $runner = new TestRunner();
        require __DIR__ . '/integration.php';
        $exitCode = $runner->finish();
    } else {
        foreach ($environment->browserEnvironment() as $key => $value) {
            putenv($key . '=' . $value);
        }
        $root = dirname(__DIR__);
        $cli = $root . '/node_modules/@playwright/test/cli.js';
        if (!is_file($cli)) throw new RuntimeException('Install development dependencies with npm install first.');
        $process = proc_open(['node', $cli, 'test'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
        if (!is_resource($process)) throw new RuntimeException('Cannot launch browser tests.');
        $exitCode = proc_close($process);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Tests could not complete: ' . $error->getMessage() . "\nCheck that local MySQL is running and test dependencies are installed.\n");
} finally {
    if ($environment !== null) {
        try {
            $environment->close();
            print "Temporary test database and server removed.\n";
        } catch (Throwable $error) {
            $exitCode = 1;
            fwrite(STDERR, 'Cleanup could not complete: ' . $error->getMessage() . "\n");
        }
    }
}
exit($exitCode);
