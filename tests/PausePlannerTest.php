<?php

declare(strict_types=1);

use CallboxAutomation\Domain\PausePlanner;
use CallboxAutomation\Domain\PauseTemplate;
use CallboxAutomation\Exception\MissingOperatingEntryException;
use CallboxAutomation\Exception\NoFreePriorityException;

/** @return array<int,array<string,mixed>> */
function planoBase(int $operatingPriority = 4, array $extra = []): array
{
    return array_merge($extra, [
        [
            'prioridade' => $operatingPriority,
            'tipo' => 'Funcionamento',
            'mes' => 'Todos',
            'dia' => 'Todos',
            'dia_semana' => 'Segunda a sexta',
            'horario' => '08:00-17:59',
            'opcao' => 'Atendimento A',
            'audio' => '',
            'campo_desconhecido' => 'preservar',
        ],
        ['tipo' => 'Transbordo', 'opcao' => 'Fora do horário'],
    ]);
}

function feriado(int $mes = 9, int $dia = 7, string $audio = 'pausa_feriado_0709'): PauseTemplate
{
    return new PauseTemplate('Pausa de feriado', $mes, $dia, 'Segunda', '00:00-23:59', 'Desligar', $audio);
}

function pausaExistente(int $prioridade, int $mes, int $dia): array
{
    return feriado($mes, $dia, "pausa_{$mes}_{$dia}")->toEntry($prioridade);
}

test('planner: pausa nova usa a menor prioridade livre abaixo do funcionamento', function (): void {
    $change = (new PausePlanner())->addPauses(planoBase(4), [feriado()]);

    assertSame(1, $change->added[0]['prioridade']);
    assertSame(3, count($change->after));
});

test('planner: prioridade de funcionamento e campos desconhecidos ficam intactos', function (): void {
    $before = planoBase(4);
    $change = (new PausePlanner())->addPauses($before, [feriado()]);

    assertSame($before[0], $change->after[0]);
    assertSame('preservar', $change->after[0]['campo_desconhecido']);
    assertSame($before[1], $change->after[1]);
});

test('planner: o número do funcionamento pode variar entre planos', function (): void {
    $change = (new PausePlanner())->addPauses(planoBase(7), [feriado()]);

    assertSame(1, $change->added[0]['prioridade']);
    assertSame(7, $change->after[0]['prioridade']);
});

test('planner: pula números já ocupados', function (): void {
    $current = planoBase(4, [pausaExistente(1, 4, 21), pausaExistente(2, 5, 1)]);
    $change = (new PausePlanner())->addPauses($current, [feriado()]);

    assertSame(3, $change->added[0]['prioridade']);
});

test('planner: aplicar o mesmo modelo duas vezes não duplica', function (): void {
    $planner = new PausePlanner();
    $first = $planner->addPauses(planoBase(4), [feriado()]);
    $second = $planner->addPauses($first->after, [feriado()]);

    assertTrue($second->isEmpty(), 'segunda aplicação deveria ser vazia');
    assertSame(1, count($second->skipped));
});

test('planner: sem prioridade de funcionamento nada é planejado', function (): void {
    assertThrows(
        MissingOperatingEntryException::class,
        fn () => (new PausePlanner())->addPauses([['tipo' => 'Transbordo']], [feriado()]),
    );
});

test('planner: sem prioridade livre abaixo do funcionamento falha em vez de renumerar', function (): void {
    $current = planoBase(3, [pausaExistente(1, 4, 21), pausaExistente(2, 5, 1)]);

    assertThrows(NoFreePriorityException::class, fn () => (new PausePlanner())->addPauses($current, [feriado()]));
});

test('planner: remove só pausas vencidas e preserva funcionamento e demais entradas', function (): void {
    $current = planoBase(4, [pausaExistente(1, 4, 21), pausaExistente(2, 12, 25)]);
    $change = (new PausePlanner())->removeExpired($current, new DateTimeImmutable('2026-10-23'));

    assertSame(1, count($change->removed));
    assertSame(4, $change->removed[0]['mes']);
    assertSame(3, count($change->after));
    assertSame($current[2], $change->after[1]);
});

test('planner: pausa do dia de hoje não é removida', function (): void {
    $current = planoBase(4, [pausaExistente(1, 10, 23)]);
    $change = (new PausePlanner())->removeExpired($current, new DateTimeImmutable('2026-10-23'));

    assertTrue($change->isEmpty());
});
