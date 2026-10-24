<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/support/TestRunner.php';

$runner = new TestRunner();
foreach ([-5 => 0, 0 => 0, 1 => 100, 7 => 700, 49 => 4900, 50 => 5000, 51 => 5000, 1000 => 5000] as $days => $expected) {
    $runner->test("Fine for $days days late", function () use ($days, $expected): void {
        TestRunner::same($expected, Fine::calculate($days));
    });
}
foreach ([[0, '0.00'], [700, '700.00'], [12.345, '12.35'], [5000, '5000.00']] as [$amount, $expected]) {
    $runner->test("Format amount $amount", function () use ($amount, $expected): void {
        TestRunner::same($expected, Fine::formatAmount($amount));
    });
}
$runner->test('AppException preserves its message and HTTP status', function (): void {
    $error = new AppException('Not found.', 404);
    TestRunner::same('Not found.', $error->getMessage());
    TestRunner::same(404, $error->getHttpStatus());
});
exit($runner->finish());
