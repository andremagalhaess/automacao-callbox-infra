<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$passed = 0;
$failed = 0;

foreach ($GLOBALS['__tests'] as $name => $fn) {
    try {
        $fn();
        $passed++;
        echo "  ok   {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  FAIL {$name}\n       " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

echo sprintf("\n%d teste(s): %d ok, %d falha(s)\n", $passed + $failed, $passed, $failed);
exit($failed > 0 ? 1 : 0);
