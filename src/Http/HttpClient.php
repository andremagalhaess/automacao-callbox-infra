<?php

declare(strict_types=1);

namespace CallboxAutomation\Http;

interface HttpClient
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|null $body enviado como JSON
     */
    public function send(string $method, string $url, array $headers = [], ?array $body = null): HttpResponse;
}
