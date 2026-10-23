<?php

declare(strict_types=1);

namespace CallboxAutomation\Support;

final class BatchReport
{
    /** @var array<int,array{plano:string,status:string,detalhe:string}> */
    private array $rows = [];

    public function __construct(
        public readonly string $runId,
        public readonly bool $dryRun,
    ) {
    }

    public function add(string $plan, string $status, string $detail = ''): void
    {
        $this->rows[] = ['plano' => $plan, 'status' => $status, 'detalhe' => $detail];
    }

    /** @return array<int,array{plano:string,status:string,detalhe:string}> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function count(string $status): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => $row['status'] === $status));
    }

    public function hasErrors(): bool
    {
        return $this->count('erro') > 0;
    }

    public function toText(): string
    {
        $mode = $this->dryRun ? 'PRÉVIA (nada foi gravado)' : 'APLICADO';
        $lines = ["Execução {$this->runId} | {$mode}"];
        foreach ($this->rows as $row) {
            $lines[] = sprintf('- %s: %s%s', $row['plano'], $row['status'], $row['detalhe'] !== '' ? " ({$row['detalhe']})" : '');
        }

        return implode("\n", $lines);
    }
}
