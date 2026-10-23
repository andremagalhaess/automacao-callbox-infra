<?php

declare(strict_types=1);

namespace CallboxAutomation\Domain;

use CallboxAutomation\Exception\MissingOperatingEntryException;
use CallboxAutomation\Exception\NoFreePriorityException;
use CallboxAutomation\Exception\ProtectedPriorityException;

/**
 * Calcula, sem tocar na rede, como ficaria um Plano de Horários.
 *
 * Regras:
 *  - pausas usam números de prioridade menores que o do funcionamento;
 *  - a prioridade de funcionamento nunca é editada nem apagada;
 *  - campos desconhecidos de cada entrada são preservados;
 *  - aplicar o mesmo modelo duas vezes não duplica a pausa.
 */
final class PausePlanner
{
    /**
     * @param array<int,array<string,mixed>> $current
     * @param array<int,PauseTemplate> $templates
     */
    public function addPauses(array $current, array $templates): PlanChange
    {
        $operating = $this->operatingPriority($current);
        $used = $this->usedPriorities($current);
        $after = $current;
        $added = [];
        $skipped = [];

        foreach ($templates as $template) {
            if ($this->alreadyPresent($after, $template)) {
                $skipped[] = $template->describe();
                continue;
            }

            $priority = $this->nextFree($used, $operating);
            $entry = $template->toEntry($priority);
            $after[] = $entry;
            $used[] = $priority;
            $added[] = $entry;
        }

        $this->assertOperatingUntouched($current, $after);

        return new PlanChange($current, $after, $added, [], $skipped);
    }

    /**
     * Remove pausas cuja data já passou neste ano. O plano não guarda o ano, então só entram
     * pausas com mês e dia numéricos; as demais entradas nunca são removidas.
     *
     * @param array<int,array<string,mixed>> $current
     */
    public function removeExpired(array $current, \DateTimeImmutable $today): PlanChange
    {
        $this->operatingPriority($current);

        $after = [];
        $removed = [];
        foreach ($current as $entry) {
            if ($this->isPause($entry) && $this->isExpired($entry, $today)) {
                $removed[] = $entry;
                continue;
            }
            $after[] = $entry;
        }

        $this->assertOperatingUntouched($current, $after);

        return new PlanChange($current, $after, [], $removed);
    }

    /** @param array<string,mixed> $entry */
    public function isOperating(array $entry): bool
    {
        return str_starts_with($this->normalizedType($entry), 'funcionamento');
    }

    /** @param array<string,mixed> $entry */
    public function isPause(array $entry): bool
    {
        return str_starts_with($this->normalizedType($entry), 'pausa');
    }

    /** @param array<int,array<string,mixed>> $current */
    private function operatingPriority(array $current): int
    {
        $priorities = [];
        foreach ($current as $entry) {
            if ($this->isOperating($entry) && isset($entry['prioridade']) && is_numeric($entry['prioridade'])) {
                $priorities[] = (int) $entry['prioridade'];
            }
        }

        if ($priorities === []) {
            throw new MissingOperatingEntryException(
                'O plano não tem prioridade de funcionamento válida; nada será gravado.'
            );
        }

        return min($priorities);
    }

    /**
     * @param array<int,array<string,mixed>> $current
     * @return array<int,int>
     */
    private function usedPriorities(array $current): array
    {
        $used = [];
        foreach ($current as $entry) {
            if (isset($entry['prioridade']) && is_numeric($entry['prioridade'])) {
                $used[] = (int) $entry['prioridade'];
            }
        }

        return $used;
    }

    /** @param array<int,int> $used */
    private function nextFree(array $used, int $operating): int
    {
        for ($number = 1; $number < $operating; $number++) {
            if (!in_array($number, $used, true)) {
                return $number;
            }
        }

        throw new NoFreePriorityException(
            "Sem prioridade livre abaixo do funcionamento ({$operating}); a prioridade de funcionamento não é renumerada."
        );
    }

    /** @param array<int,array<string,mixed>> $entries */
    private function alreadyPresent(array $entries, PauseTemplate $template): bool
    {
        foreach ($entries as $entry) {
            if ($this->isPause($entry) && $template->matches($entry)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $entry */
    private function isExpired(array $entry, \DateTimeImmutable $today): bool
    {
        $month = $entry['mes'] ?? null;
        $day = $entry['dia'] ?? null;
        if (!is_numeric($month) || !is_numeric($day)) {
            return false;
        }

        $todayMonth = (int) $today->format('n');
        $todayDay = (int) $today->format('j');

        return (int) $month < $todayMonth || ((int) $month === $todayMonth && (int) $day < $todayDay);
    }

    /**
     * @param array<int,array<string,mixed>> $before
     * @param array<int,array<string,mixed>> $after
     */
    private function assertOperatingUntouched(array $before, array $after): void
    {
        $pick = fn (array $entries): array => array_values(array_filter($entries, fn (array $e): bool => $this->isOperating($e)));

        if ($pick($before) !== $pick($after)) {
            throw new ProtectedPriorityException('A alteração mexeria na prioridade de funcionamento e foi bloqueada.');
        }
    }

    /** @param array<string,mixed> $entry */
    private function normalizedType(array $entry): string
    {
        return mb_strtolower(trim((string) ($entry['tipo'] ?? '')));
    }
}
