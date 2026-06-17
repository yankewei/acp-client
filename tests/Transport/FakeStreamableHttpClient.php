<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Transport;

use Yankewei\AcpClient\Transport\StreamableHttpClientInterface;
use Yankewei\AcpClient\Transport\StreamableHttpResponse;

final class FakeStreamableHttpClient implements StreamableHttpClientInterface
{
    /**
     * @var list<array{
     *     url: string,
     *     body: string,
     *     headers: array<string, string>,
     *     timeout: float
     * }>
     */
    public array $requests = [];

    public function __construct(
        private readonly StreamableHttpResponse $response,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, string $body, array $headers, float $timeout): StreamableHttpResponse
    {
        $this->requests[] = [
            'url' => $url,
            'body' => $body,
            'headers' => $headers,
            'timeout' => $timeout,
        ];

        return $this->response;
    }
}
