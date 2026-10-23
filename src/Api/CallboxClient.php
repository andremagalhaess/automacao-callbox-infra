<?php

declare(strict_types=1);

namespace CallboxAutomation\Api;

use CallboxAutomation\Config;
use CallboxAutomation\Exception\CallboxException;
use CallboxAutomation\Http\HttpClient;
use CallboxAutomation\Http\HttpResponse;

/** Autenticação por token Bearer e envio de requisições; renova o token uma vez em caso de 401. */
final class CallboxClient
{
    private ?string $token = null;

    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {
    }

    public function login(): void
    {
        $response = $this->http->send('POST', $this->url('/login'), [], [
            'login' => $this->config->login,
            'senha' => $this->config->password,
            'product' => $this->config->product,
            'device' => $this->config->device,
        ]);

        $token = $response->data['token'] ?? null;
        if (!$response->ok() || !is_string($token) || $token === '') {
            // Nunca inclui credenciais na mensagem.
            throw new CallboxException("Falha no login (HTTP {$response->status}).");
        }
        $this->token = $token;
    }

    /**
     * @param array<string,string> $search vira headers "search.<chave>" para localizar o registro
     * @param array<string,mixed>|null $body
     */
    public function request(string $method, string $path, array $search = [], ?array $body = null): HttpResponse
    {
        if ($this->token === null) {
            $this->login();
        }

        $response = $this->dispatch($method, $path, $search, $body);

        if ($response->status === 401) {
            $this->token = null;
            $this->login();
            $response = $this->dispatch($method, $path, $search, $body);
        }

        return $response;
    }

    /**
     * @param array<string,string> $search
     * @param array<string,mixed>|null $body
     */
    private function dispatch(string $method, string $path, array $search, ?array $body): HttpResponse
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];
        foreach ($search as $key => $value) {
            $headers["search.{$key}"] = $value;
        }

        return $this->http->send($method, $this->url($path), $headers, $body);
    }

    private function url(string $path): string
    {
        return $this->config->baseUrl . '/' . ltrim($path, '/');
    }
}
