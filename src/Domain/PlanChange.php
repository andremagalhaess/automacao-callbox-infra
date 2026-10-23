<?php

declare(strict_types=1);

namespace CallboxAutomation\Domain;

/** Resultado do planejamento: o plano antes e depois, e o que foi adicionado, removido ou ignorado. */
final class PlanChange
{
    /**
     * @param array<int,array<string,mixed>> $before
     * @param array<int,array<string,mixed>> $after
     * @param array<int,array<string,mixed>> $added
     * @param array<int,array<string,mixed>> $removed
     * @param array<int,string> $skipped pausas que já existiam
     */
    public function __construct(
        public readonly array $before,
        public readonly array $after,
        public readonly array $added = [],
        public readonly array $removed = [],
        public readonly array $skipped = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->added === [] && $this->removed === [];
    }

    public function summary(): string
    {
        $parts = [];
        if ($this->added !== []) {
            $parts[] = '+' . count($this->added) . ' pausa(s)';
        }
        if ($this->removed !== []) {
            $parts[] = '-' . count($this->removed) . ' pausa(s) vencida(s)';
        }
        if ($this->skipped !== []) {
            $parts[] = count($this->skipped) . ' já existia(m)';
        }

        return $parts === [] ? 'sem alterações' : implode(', ', $parts);
    }
}
