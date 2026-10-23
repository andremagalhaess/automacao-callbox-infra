#!/usr/bin/env php
<?php

declare(strict_types=1);

use CallboxAutomation\Api\CallboxClient;
use CallboxAutomation\Api\ScheduleApi;
use CallboxAutomation\Config;
use CallboxAutomation\Domain\PausePlanner;
use CallboxAutomation\Domain\PauseTemplate;
use CallboxAutomation\Exception\CallboxException;
use CallboxAutomation\Http\CurlHttpClient;
use CallboxAutomation\Service\PauseBatchService;
use CallboxAutomation\Support\BatchLog;
use CallboxAutomation\Support\EnvFile;

$root = dirname(__DIR__);
require is_file($root . '/vendor/autoload.php') ? $root . '/vendor/autoload.php' : $root . '/autoload.php';

const USAGE = <<<TXT
Uso:
  php bin/callbox-pausas.php adicionar        --planos=planos.json --modelo=feriado.json [--aplicar]
  php bin/callbox-pausas.php limpar-vencidas  --planos=planos.json [--aplicar]
  php bin/callbox-pausas.php reverter         --execucao=ID [--aplicar]

Por padrão tudo roda em PRÉVIA. Para gravar são necessários DOIS comandos explícitos:
CALLBOX_DRY_RUN=false no .env E a opção --aplicar.

TXT;

$command = $argv[1] ?? 'ajuda';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $match)) {
        $options[$match[1]] = $match[2] ?? true;
    }
}

/** @return array<mixed> */
$readJson = static function (string $path): array {
    if (!is_file($path)) {
        throw new CallboxException("Arquivo não encontrado: {$path}");
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new CallboxException("JSON inválido: {$path}");
    }

    return $data;
};

try {
    if (!in_array($command, ['adicionar', 'limpar-vencidas', 'reverter'], true)) {
        fwrite(STDOUT, USAGE);
        exit($command === 'ajuda' ? 0 : 2);
    }

    $config = Config::fromEnv(array_merge(EnvFile::load($root . '/.env'), getenv()));
    $apply = isset($options['aplicar']);
    $dryRun = $config->dryRun || !$apply;

    if ($apply && $config->dryRun) {
        fwrite(STDERR, "Aviso: CALLBOX_DRY_RUN=true no .env; nada será gravado.\n");
    }

    $service = new PauseBatchService(
        new ScheduleApi(new CallboxClient(new CurlHttpClient(), $config)),
        new PausePlanner(),
        new BatchLog($root . '/logs'),
        $dryRun,
        $config->batchLimit,
    );

    $plans = static fn (): array => array_map('strval', $readJson((string) ($options['planos'] ?? '')));

    $report = match ($command) {
        'adicionar' => $service->addPauses(
            $plans(),
            (static function () use ($readJson, $options): array {
                $data = $readJson((string) ($options['modelo'] ?? ''));
                $list = array_is_list($data) ? $data : [$data];

                return array_map(static fn (array $item): PauseTemplate => PauseTemplate::fromArray($item), $list);
            })(),
        ),
        'limpar-vencidas' => $service->removeExpired($plans(), new DateTimeImmutable('today')),
        'reverter' => $service->rollback((string) ($options['execucao'] ?? '')),
    };

    fwrite(STDOUT, $report->toText() . "\n");
    exit($report->hasErrors() ? 1 : 0);
} catch (CallboxException $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(2);
}
