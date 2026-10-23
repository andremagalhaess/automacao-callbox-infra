<?php

declare(strict_types=1);

namespace CallboxAutomation\Tests\Support;

use CallboxAutomation\Http\HttpClient;
use CallboxAutomation\Http\HttpResponse;

/**
 * Servidor falso em memória. O PATCH SUBSTITUI a lista inteira de sub_opcoes, de propósito:
 * é o pior caso descrito na documentação, e os testes provam que o código se protege dele.
 */
final class FakeCallboxServer implements HttpClient
{
    /** @var array<int,array{method:string,url:string,headers:array<string,string>,body:?array}> */
    public array $requests = [];
    public int $logins = 0;
    public int $patches = 0;
    private string $token = '';

    /**
     * @param array<string,array<int,array<string,mixed>>> $plans
     * @param array<int,string> $failPatchFor
     */
    public function __construct(
        public array $plans = [],
        private readonly array $failPatchFor = [],
        public bool $expireTokenOnce = false,
    ) {
    }

    public function send(string $method, string $url, array $headers = [], ?array $body = null): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($method === 'POST' && str_ends_with($path, '/login')) {
            $this->logins++;
            $this->token = 'token-' . $this->logins;

            return new HttpResponse(200, ['token' => $this->token]);
        }

        $authorized = $this->token !== '' && ($headers['Authorization'] ?? '') === 'Bearer ' . $this->token;
        if ($this->expireTokenOnce) {
            $this->expireTokenOnce = false;
            $authorized = false;
        }
        if (!$authorized) {
            return new HttpResponse(401);
        }

        $name = $headers['search.name'] ?? '';
        if (!str_ends_with($path, '/configuracoes/planodehorarios') || !isset($this->plans[$name])) {
            return new HttpResponse(404);
        }

        if ($method === 'GET') {
            return new HttpResponse(200, ['sub_opcoes' => $this->plans[$name]]);
        }

        if ($method === 'PATCH') {
            if (in_array($name, $this->failPatchFor, true)) {
                return new HttpResponse(500);
            }
            $this->patches++;
            $this->plans[$name] = $body['sub_opcoes'] ?? [];

            return new HttpResponse(200);
        }

        return new HttpResponse(405);
    }
}
