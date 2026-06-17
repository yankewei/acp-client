<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Transport;

use Yankewei\AcpClient\Exception\TransportException;

interface StreamableHttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @throws TransportException
     */
    public function post(string $url, string $body, array $headers, float $timeout): StreamableHttpResponse;
}
