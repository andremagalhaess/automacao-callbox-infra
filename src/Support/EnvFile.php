<?php

declare(strict_types=1);

namespace CallboxAutomation\Support;

/** Leitor simples de arquivo .env (CHAVE=valor, # comentários). */
final class EnvFile
{
    /** @return array<string,string> */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\"'");
        }

        return $values;
    }
}
