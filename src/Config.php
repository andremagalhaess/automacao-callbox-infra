<?php

declare(strict_types=1);

namespace CallboxAutomation;

use CallboxAutomation\Exception\CallboxException;

final class Config
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $login,
        public readonly string $password,
        public readonly string $product,
        public readonly string $device,
        public readonly bool $dryRun,
        public readonly int $batchLimit,
    ) {
    }

    /** @param array<string,mixed> $env */
    public static function fromEnv(array $env): self
    {
        $get = static fn (string $key, string $default = ''): string => trim((string) ($env[$key] ?? $default));

        foreach (['CALLBOX_BASE_URL', 'CALLBOX_LOGIN', 'CALLBOX_PASSWORD'] as $required) {
            if ($get($required) === '') {
                throw new CallboxException("Variável de ambiente obrigatória ausente: {$required}");
            }
        }

        $limit = (int) $get('CALLBOX_BATCH_LIMIT', '50');

        return new self(
            baseUrl: rtrim($get('CALLBOX_BASE_URL'), '/'),
            login: $get('CALLBOX_LOGIN'),
            password: $get('CALLBOX_PASSWORD'),
            product: $get('CALLBOX_PRODUCT'),
            device: $get('CALLBOX_DEVICE'),
            // Seguro por padrão: só grava se o .env disser explicitamente que não é dry-run.
            dryRun: filter_var($get('CALLBOX_DRY_RUN', 'true'), FILTER_VALIDATE_BOOLEAN),
            batchLimit: $limit > 0 ? $limit : 50,
        );
    }
}
