<?php

declare(strict_types=1);

namespace CallboxAutomation\Support;

use CallboxAutomation\Exception\CallboxException;

/** Log por plano em JSON Lines (um arquivo por execução), usado também para reverter. */
final class BatchLog
{
    public function __construct(private readonly string $directory)
    {
    }

    public function newRunId(): string
    {
        return gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }

    /** @param array<string,mixed> $row */
    public function record(string $runId, array $row): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new CallboxException('Não foi possível criar a pasta de logs.');
        }

        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($this->path($runId), $line, FILE_APPEND | LOCK_EX);
    }

    /** @return array<int,array<string,mixed>> */
    public function load(string $runId): array
    {
        $path = $this->path($runId);
        if (!is_file($path)) {
            throw new CallboxException("Execução '{$runId}' não encontrada nos logs.");
        }

        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    private function path(string $runId): string
    {
        if (!preg_match('/^[0-9A-Za-z-]+$/', $runId)) {
            throw new CallboxException('Identificador de execução inválido.');
        }

        return rtrim($this->directory, '/') . "/{$runId}.jsonl";
    }
}
