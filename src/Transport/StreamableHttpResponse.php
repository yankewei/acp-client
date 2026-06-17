<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

final class StreamableHttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body,
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContentType(): string
    {
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return strtolower($value);
            }
        }

        return '';
    }
}
