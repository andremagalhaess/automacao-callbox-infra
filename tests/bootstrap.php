<?php

declare(strict_types=1);

// Mini framework de testes sem dependências: roda com `php tests/run.php`.

require_once __DIR__ . '/../autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'CallboxAutomation\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/** @var array<string,callable> $GLOBALS['__tests'] */
$GLOBALS['__tests'] = [];
$GLOBALS['__tempDirs'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][$name] = $fn;
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionError(sprintf(
            '%s esperado=%s obtido=%s',
            $message !== '' ? $message . ':' : '',
            json_encode($expected, JSON_UNESCAPED_UNICODE),
            json_encode($actual, JSON_UNESCAPED_UNICODE),
        ));
    }
}

function assertTrue(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new AssertionError($message !== '' ? $message : 'condição falsa');
    }
}

function assertThrows(string $class, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new AssertionError("esperava {$class}, veio " . $e::class . ': ' . $e->getMessage());
    }
    throw new AssertionError("esperava {$class}, mas nada foi lançado");
}

function tempDir(): string
{
    $dir = sys_get_temp_dir() . '/callbox-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    $GLOBALS['__tempDirs'][] = $dir;

    return $dir;
}

register_shutdown_function(static function (): void {
    foreach ($GLOBALS['__tempDirs'] as $dir) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
});
