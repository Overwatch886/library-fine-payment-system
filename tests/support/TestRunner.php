<?php
/* Small explicit assertions: unlike PHP assert(), these cannot be disabled by configuration. */
class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            print "PASS: $name\n";
        } catch (Throwable $error) {
            $this->failed++;
            print "FAIL: $name — " . $error->getMessage() . "\n";
        }
    }

    public static function same(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    public static function truth(bool $value, string $message = 'Expected condition to be true'): void
    {
        if (!$value) throw new RuntimeException($message);
    }

    public function finish(): int
    {
        print "\n{$this->passed} passed, {$this->failed} failed\n";
        return $this->failed === 0 ? 0 : 1;
    }
}
