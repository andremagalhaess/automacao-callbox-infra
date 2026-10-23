<?php

declare(strict_types=1);

namespace CallboxAutomation\Http;

use CallboxAutomation\Exception\CallboxException;

final class CurlHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutSeconds = 20)
    {
    }

    public function send(string $method, string $url, array $headers = [], ?array $body = null): HttpResponse
    {
        $lines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ];

        if ($body !== null) {
            $lines[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $options[CURLOPT_HTTPHEADER] = $lines;

        $handle = curl_init($url);
        if ($handle === false) {
            throw new CallboxException('Não foi possível iniciar a requisição HTTP.');
        }
        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        if ($raw === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new CallboxException("Falha de rede: {$error}");
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $decoded = json_decode((string) $raw, true);

        return new HttpResponse($status, is_array($decoded) ? $decoded : []);
    }
}
