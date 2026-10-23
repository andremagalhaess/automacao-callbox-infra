<?php

declare(strict_types=1);

use CallboxAutomation\Api\CallboxClient;
use CallboxAutomation\Config;
use CallboxAutomation\Exception\CallboxException;
use CallboxAutomation\Tests\Support\FakeCallboxServer;

test('cliente: faz login uma vez e envia o token Bearer', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4)]);
    $client = new CallboxClient($server, configTeste());

    $client->request('GET', '/configuracoes/planodehorarios', ['name' => 'Atendimento A']);
    $client->request('GET', '/configuracoes/planodehorarios', ['name' => 'Atendimento A']);

    assertSame(1, $server->logins);
    $last = end($server->requests);
    assertSame('Bearer token-1', $last['headers']['Authorization']);
    assertSame('Atendimento A', $last['headers']['search.name']);
});

test('cliente: renova o token uma vez quando recebe 401', function (): void {
    $server = new FakeCallboxServer(['Atendimento A' => planoBase(4)]);
    $client = new CallboxClient($server, configTeste());

    $client->request('GET', '/configuracoes/planodehorarios', ['name' => 'Atendimento A']);
    $server->expireTokenOnce = true;
    $response = $client->request('GET', '/configuracoes/planodehorarios', ['name' => 'Atendimento A']);

    assertTrue($response->ok(), 'deveria ter reautenticado e funcionado');
    assertSame(2, $server->logins);
});

test('cliente: mensagem de erro de login não vaza credenciais', function (): void {
    $http = new class implements \CallboxAutomation\Http\HttpClient {
        public function send(string $method, string $url, array $headers = [], ?array $body = null): \CallboxAutomation\Http\HttpResponse
        {
            return new \CallboxAutomation\Http\HttpResponse(403);
        }
    };

    try {
        (new CallboxClient($http, configTeste()))->login();
        throw new AssertionError('deveria ter falhado');
    } catch (CallboxException $e) {
        assertTrue(!str_contains($e->getMessage(), 'senha-teste'), 'senha apareceu na mensagem');
        assertTrue(!str_contains($e->getMessage(), 'usuario-teste'), 'login apareceu na mensagem');
    }
});

test('config: variáveis obrigatórias ausentes são recusadas e dry-run é o padrão', function (): void {
    assertThrows(CallboxException::class, fn () => Config::fromEnv(['CALLBOX_BASE_URL' => 'https://x.test']));

    $config = Config::fromEnv([
        'CALLBOX_BASE_URL' => 'https://x.test/api/',
        'CALLBOX_LOGIN' => 'a',
        'CALLBOX_PASSWORD' => 'b',
    ]);
    assertTrue($config->dryRun, 'dry-run deveria ser o padrão');
    assertSame('https://x.test/api', $config->baseUrl);
});
