<?php
/** A tiny test harness. No Composer needed. */

$GLOBALS['mx_tests'] = [];
$GLOBALS['mx_skipped'] = 0;

function test(string $name, callable $fn): void
{
    $GLOBALS['mx_tests'][] = [$name, $fn];
}

function assert_true($value, string $message = 'Expected true'): void
{
    if ($value !== true) {
        throw new AssertionError($message);
    }
}

function assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionError(($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

/** Run $fn and require it to throw RuntimeException with a message containing $needle. */
function assert_refused(callable $fn, string $needle): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        if (stripos($e->getMessage(), $needle) === false) {
            throw new AssertionError('Refused with "' . $e->getMessage() . '", expected text "' . $needle . '"');
        }
        return;
    }
    throw new AssertionError('Expected the action to be refused with "' . $needle . '"');
}

function run_tests(): int
{
    $failed = 0;
    foreach ($GLOBALS['mx_tests'] as [$name, $fn]) {
        try {
            $fn();
            echo "  ok    $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
        }
    }
    $total = count($GLOBALS['mx_tests']);
    echo "\n" . ($total - $failed) . " of $total passed";
    echo $failed ? ", $failed failed\n" : "\n";
    return $failed ? 1 : 0;
}
