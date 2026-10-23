<?php

declare(strict_types=1);

namespace CallboxAutomation\Domain;

use CallboxAutomation\Exception\CallboxException;

/** Modelo de pausa (feriado, treinamento etc.) a ser aplicado em vários planos. */
final class PauseTemplate
{
    private const REQUIRED = ['tipo', 'mes', 'dia', 'dia_semana', 'horario', 'destino', 'audio'];

    public function __construct(
        public readonly string $tipo,
        public readonly int|string $mes,
        public readonly int|string $dia,
        public readonly string $diaSemana,
        public readonly string $horario,
        public readonly string $destino,
        public readonly string $audio,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (self::REQUIRED as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                throw new CallboxException("Modelo de pausa inválido: campo '{$key}' ausente.");
            }
        }

        return new self(
            tipo: (string) $data['tipo'],
            mes: is_numeric($data['mes']) ? (int) $data['mes'] : (string) $data['mes'],
            dia: is_numeric($data['dia']) ? (int) $data['dia'] : (string) $data['dia'],
            diaSemana: (string) $data['dia_semana'],
            horario: (string) $data['horario'],
            destino: (string) $data['destino'],
            audio: (string) $data['audio'],
        );
    }

    /** @return array<string,mixed> */
    public function toEntry(int $prioridade): array
    {
        return [
            'prioridade' => $prioridade,
            'tipo' => $this->tipo,
            'mes' => $this->mes,
            'dia' => $this->dia,
            'dia_semana' => $this->diaSemana,
            'horario' => $this->horario,
            'opcao' => $this->destino,
            'audio' => $this->audio,
        ];
    }

    /** @param array<string,mixed> $entry */
    public function matches(array $entry): bool
    {
        return (string) ($entry['tipo'] ?? '') === $this->tipo
            && (string) ($entry['mes'] ?? '') === (string) $this->mes
            && (string) ($entry['dia'] ?? '') === (string) $this->dia
            && (string) ($entry['horario'] ?? '') === $this->horario
            && (string) ($entry['audio'] ?? '') === $this->audio;
    }

    public function describe(): string
    {
        return sprintf('%s %s/%s %s', $this->tipo, $this->dia, $this->mes, $this->horario);
    }
}
