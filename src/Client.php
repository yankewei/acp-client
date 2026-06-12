<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use JsonException;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Exception\JsonRpcException;
use Yankewei\AcpClient\Exception\TransportException;
use Yankewei\AcpClient\JsonRpc\Request;
use Yankewei\AcpClient\JsonRpc\Response;
use Yankewei\AcpClient\Transport\TransportInterface;

final class Client
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly float $defaultTimeout = 30.0,
    ) {
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function initialize(): array
    {
        return $this->call('initialize');
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    public function call(string $method, array $params = [], ?float $timeout = null): array
    {
        $this->transport->open();

        $request = new Request($method, Request::nextId(), $params);
        $this->transport->send($request->toJson());

        $response = $this->waitForResponse($request->getId(), $timeout ?? $this->defaultTimeout);

        if ($response === null) {
            throw new TransportException('Timeout waiting for response');
        }

        if ($response->hasError()) {
            $error = $response->getError();
            throw new JsonRpcException($error->getCode(), $error->getMessage(), $error->getData());
        }

        $result = $response->getResult();

        if (!is_array($result)) {
            throw new AcpException('Invalid JSON-RPC response: result is not an object/array');
        }

        return $result;
    }

    /**
     * @throws JsonException
     * @throws TransportException
     */
    public function notify(string $method, array $params = []): void
    {
        $this->transport->open();

        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $this->transport->send(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws AcpException
     * @throws JsonException
     * @throws TransportException
     */
    private function waitForResponse(int|string $id, float $timeout): ?Response
    {
        $end = microtime(true) + $timeout;

        do {
            $remaining = $end - microtime(true);
            if ($remaining <= 0.0) {
                return null;
            }

            $line = $this->transport->receive($remaining);

            if ($line === null) {
                return null;
            }

            $response = Response::fromJson($line);

            if ($response->getId() === $id) {
                return $response;
            }
        } while (true);
    }
}
