<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

use Yankewei\AcpClient\Exception\TransportException;

final class NativeStreamableHttpClient implements StreamableHttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @throws TransportException
     */
    public function post(string $url, string $body, array $headers, float $timeout): StreamableHttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->formatHeaders($headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        /** @var string[] $http_response_header */
        $http_response_header = [];

        $responseBody = file_get_contents($url, use_include_path: false, context: $context);
        if ($responseBody === false) {
            throw new TransportException('Failed to send streamable HTTP request');
        }

        return new StreamableHttpResponse(
            $this->statusCode($http_response_header),
            $this->headers($http_response_header),
            $responseBody,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param string[] $headers
     *
     * @throws TransportException
     */
    private function statusCode(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        $matches = [];
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $statusLine, $matches) !== 1) {
            throw new TransportException('Invalid streamable HTTP response status');
        }

        return (int) $matches[1];
    }

    /**
     * @param string[] $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            if (!str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $header, limit: 2);
            $parsed[trim($name)] = trim($value);
        }

        return $parsed;
    }
}
