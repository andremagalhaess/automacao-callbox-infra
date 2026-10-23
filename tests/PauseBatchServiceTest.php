<?php

declare(strict_types=1);

use CallboxAutomation\Api\CallboxClient;
use CallboxAutomation\Api\ScheduleApi;
use CallboxAutomation\Config;
use CallboxAutomation\Domain\PausePlanner;
use CallboxAutomation\Exception\CallboxException;
use CallboxAutomation\Service\PauseBatchService;
use CallboxAutomation\Support\BatchLog;
use CallboxAutomation\Tests\Support\FakeCallboxServer;

function configTeste(): Config
{
    return Config::fromEnv([
        'CALLBOX_BASE_URL' => 'https://callbox.example.test/callbox-api',
        'CALLBOX_LOGIN' => 'usuario-teste',
        'CALLBOX_PASSWORD' => 'senha-teste',
        'CALLBOX_PRODUCT' => 'produto',
        'CALLBOX_DEVICE' => 'dispositivo',
        'CALLBOX_DRY_RUN' => 'false',
    ]);
}

function criarServico(FakeCallboxServer $server, bool $dryRun, int $limit = 50): PauseBatchService
{
    $api = new ScheduleApi(new CallboxClient($server, configTeste()));

    return new PauseBatchService($api, new PausePlanner(), new BatchLog(tempDir()), $dryRun, $limit);
}

test('lote: dry-run não grava nada e mostra prévia', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4), 'Atendimento B' => planoBase(5)]);
    $report = criarServico($server, true)->addPauses(['Atendimento A', 'Atendimento B'], [feriado()]);

    assertSame(0, $server->patches);
    assertSame(2, $report->count('prévia'));
    assertSame(planoBase(4), $server->plans['Atendimento A']);
});

test('lote: aplicar envia a lista completa e o funcionamento sobrevive', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4)]);
    $report = criarServico($server, false)->addPauses(['Atendimento A'], [feriado()]);

    assertSame(1, $report->count('aplicado'));
    assertSame(1, $server->patches);

    $saved = $server->plans['Atendimento A'];
    assertSame(3, count($saved));
    assertSame(planoBase(4)[0], $saved[0], 'funcionamento alterado');
    assertSame('Transbordo', $saved[1]['tipo']);
});

test('lote: erro em um plano não interrompe os outros', function (): void {
    $server = new FakeCallboxServer(
        ['Atendimento A' => planoBase(4), 'Atendimento B' => planoBase(4), 'Atendimento C' => planoBase(4)],
        ['Atendimento B'],
    );
    $report = criarServico($server, false)->addPauses(
        ['Atendimento A', 'Atendimento B', 'Atendimento C'],
        [feriado()],
    );

    assertSame(2, $report->count('aplicado'));
    assertSame(1, $report->count('erro'));
    assertTrue($report->hasErrors());
    assertSame(planoBase(4), $server->plans['Atendimento B'], 'plano com erro deveria ficar intacto');
});

test('lote: plano inexistente vira erro no relatório', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4)]);
    $report = criarServico($server, false)->addPauses(['Atendimento A', 'Atendimento X'], [feriado()]);

    assertSame(1, $report->count('aplicado'));
    assertSame(1, $report->count('erro'));
});

test('lote: respeita o limite de planos por execução', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4), 'Atendimento B' => planoBase(4)]);

    assertThrows(
        CallboxException::class,
        fn () => criarServico($server, false, 1)->addPauses(['Atendimento A', 'Atendimento B'], [feriado()]),
    );
    assertSame(0, $server->patches);
});

test('lote: reverter restaura o estado anterior dos planos alterados', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4), 'Atendimento B' => planoBase(6)]);
    $api = new ScheduleApi(new CallboxClient($server, configTeste()));
    $log = new BatchLog(tempDir());
    $service = new PauseBatchService($api, new PausePlanner(), $log, false);

    $applied = $service->addPauses(['Atendimento A', 'Atendimento B'], [feriado()]);
    assertSame(3, count($server->plans['Atendimento A']));

    $rollback = $service->rollback($applied->runId);

    assertSame(2, $rollback->count('revertido'));
    assertSame(planoBase(4), $server->plans['Atendimento A']);
    assertSame(planoBase(6), $server->plans['Atendimento B']);
});

test('lote: reverter em dry-run só mostra prévia', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4)]);
    $api = new ScheduleApi(new CallboxClient($server, configTeste()));
    $log = new BatchLog(tempDir());

    $applied = (new PauseBatchService($api, new PausePlanner(), $log, false))->addPauses(['Atendimento A'], [feriado()]);
    $patchesAfterApply = $server->patches;

    $preview = (new PauseBatchService($api, new PausePlanner(), $log, true))->rollback($applied->runId);

    assertSame(1, $preview->count('prévia'));
    assertSame($patchesAfterApply, $server->patches);
});

test('lote: limpar vencidas remove só pausas passadas', function (): void {
    $server = new FakeCallboxServer([
        'Atendimento A' => planoBase(4, [pausaExistente(1, 4, 21), pausaExistente(2, 12, 25)]),
    ]);
    $report = criarServico($server, false)->removeExpired(['Atendimento A'], new DateTimeImmutable('2026-10-23'));

    assertSame(1, $report->count('aplicado'));
    $tipos = array_map(fn (array $e): string => $e['tipo'], $server->plans['Atendimento A']);
    assertSame(['Pausa de feriado', 'Funcionamento', 'Transbordo'], $tipos);
});
