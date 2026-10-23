<?php

declare(strict_types=1);

namespace CallboxAutomation\Service;

use CallboxAutomation\Api\ScheduleApi;
use CallboxAutomation\Domain\PauseTemplate;
use CallboxAutomation\Domain\PausePlanner;
use CallboxAutomation\Domain\PlanChange;
use CallboxAutomation\Exception\CallboxException;
use CallboxAutomation\Support\BatchLog;
use CallboxAutomation\Support\BatchReport;

/** Aplica pausas em vários planos com prévia (dry-run), log por plano e reversão. */
final class PauseBatchService
{
    public function __construct(
        private readonly ScheduleApi $api,
        private readonly PausePlanner $planner,
        private readonly BatchLog $log,
        private readonly bool $dryRun,
        private readonly int $batchLimit = 50,
    ) {
    }

    /**
     * @param array<int,string> $planNames
     * @param array<int,PauseTemplate> $templates
     */
    public function addPauses(array $planNames, array $templates): BatchReport
    {
        if ($templates === []) {
            throw new CallboxException('Nenhum modelo de pausa informado.');
        }

        return $this->process(
            $planNames,
            fn (array $current): PlanChange => $this->planner->addPauses($current, $templates),
        );
    }

    /** @param array<int,string> $planNames */
    public function removeExpired(array $planNames, \DateTimeImmutable $today): BatchReport
    {
        return $this->process(
            $planNames,
            fn (array $current): PlanChange => $this->planner->removeExpired($current, $today),
        );
    }

    /** Restaura o estado anterior de cada plano alterado em uma execução. */
    public function rollback(string $runId): BatchReport
    {
        $rows = $this->log->load($runId);
        $newRun = $this->log->newRunId();
        $report = new BatchReport($newRun, $this->dryRun);

        foreach ($rows as $row) {
            $name = (string) ($row['plano'] ?? '');
            if (($row['status'] ?? '') !== 'aplicado' || $name === '' || !is_array($row['antes'] ?? null)) {
                continue;
            }

            try {
                if ($this->dryRun) {
                    $report->add($name, 'prévia', 'voltaria ao estado anterior');
                } else {
                    $this->api->saveEntries($name, $row['antes']);
                    $report->add($name, 'revertido');
                    $this->log->record($newRun, ['plano' => $name, 'status' => 'revertido', 'origem' => $runId]);
                }
            } catch (\Throwable $e) {
                $report->add($name, 'erro', $e->getMessage());
            }
        }

        return $report;
    }

    /**
     * @param array<int,string> $planNames
     * @param callable(array<int,array<string,mixed>>):PlanChange $plan
     */
    private function process(array $planNames, callable $plan): BatchReport
    {
        $planNames = array_values(array_unique(array_filter($planNames, fn (string $n): bool => trim($n) !== '')));
        if ($planNames === []) {
            throw new CallboxException('Nenhum plano informado.');
        }
        if (count($planNames) > $this->batchLimit) {
            throw new CallboxException(sprintf(
                'Lote com %d planos excede o limite de %d (CALLBOX_BATCH_LIMIT).',
                count($planNames),
                $this->batchLimit,
            ));
        }

        $runId = $this->log->newRunId();
        $report = new BatchReport($runId, $this->dryRun);

        foreach ($planNames as $name) {
            try {
                $change = $plan($this->api->getEntries($name));

                if ($change->isEmpty()) {
                    $report->add($name, 'sem alteração', $change->summary());
                    $this->log->record($runId, ['plano' => $name, 'status' => 'sem alteração']);
                    continue;
                }

                if ($this->dryRun) {
                    $report->add($name, 'prévia', $change->summary());
                    $this->log->record($runId, ['plano' => $name, 'status' => 'prévia', 'resumo' => $change->summary()]);
                    continue;
                }

                $this->api->saveEntries($name, $change->after);
                $report->add($name, 'aplicado', $change->summary());
                $this->log->record($runId, [
                    'plano' => $name,
                    'status' => 'aplicado',
                    'resumo' => $change->summary(),
                    'antes' => $change->before,
                    'depois' => $change->after,
                ]);
            } catch (\Throwable $e) {
                // Um plano com erro não interrompe os demais.
                $report->add($name, 'erro', $e->getMessage());
                $this->log->record($runId, ['plano' => $name, 'status' => 'erro', 'mensagem' => $e->getMessage()]);
            }
        }

        return $report;
    }
}
