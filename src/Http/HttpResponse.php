<?php

declare(strict_types=1);

namespace CallboxAutomation\Http;

final class HttpResponse
{
    /** @param array<string,mixed> $data corpo JSON decodificado */
    public function __construct(
        public readonly int $status,
        public readonly array $data = [],
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
