<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use Throwable;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;
use Yankewei\AcpClient\Dto\RequestPermission;
use Yankewei\AcpClient\Dto\RequestPermissionOutcome;
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalCreateResult;
use Yankewei\AcpClient\Dto\Terminal\TerminalKillRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalOutputResult;
use Yankewei\AcpClient\Dto\Terminal\TerminalReleaseRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalWaitForExitRequest;
use Yankewei\AcpClient\Dto\Terminal\TerminalWaitForExitResult;

final class AgentRequestDispatcher
{
    /** @var array<string, array<int, callable(array<string, mixed>): mixed>> */
    private array $requestHandlers = [];

    /** @var (callable(string, array<string, mixed>): mixed)|null */
    private mixed $anyRequestHandler = null;

    /** @var (callable(RequestPermission): (RequestPermissionOutcome|array<string, mixed>))|null */
    private mixed $requestPermissionHandler = null;

    /** @var (callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>))|null */
    private mixed $readTextFileHandler = null;

    /** @var (callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null))|null */
    private mixed $writeTextFileHandler = null;

    /** @var (callable(TerminalCreateRequest): (TerminalCreateResult|array<string, mixed>))|null */
    private mixed $terminalCreateHandler = null;

    /** @var (callable(TerminalOutputRequest): (TerminalOutputResult|array<string, mixed>))|null */
    private mixed $terminalOutputHandler = null;

    /** @var (callable(TerminalWaitForExitRequest): (TerminalWaitForExitResult|array<string, mixed>))|null */
    private mixed $terminalWaitForExitHandler = null;

    /** @var (callable(TerminalKillRequest): (array<string, mixed>|null))|null */
    private mixed $terminalKillHandler = null;

    /** @var (callable(TerminalReleaseRequest): (array<string, mixed>|null))|null */
    private mixed $terminalReleaseHandler = null;

    /** @var array<string, array{id: int|string, sessionId: string, sendResponse: callable(int|string, mixed): void}> */
    private array $pendingPermissionRequests = [];

    /**
     * @return array{
     *     fs: array{readTextFile: bool, writeTextFile: bool},
     *     terminal: bool
     * }
     */
    public function clientCapabilities(): array
    {
        return [
            'fs' => [
                'readTextFile' => $this->readTextFileHandler !== null,
                'writeTextFile' => $this->writeTextFileHandler !== null,
            ],
            'terminal' =>
                $this->terminalCreateHandler !== null
                    || $this->terminalOutputHandler !== null
                    || $this->terminalWaitForExitHandler !== null
                    || $this->terminalKillHandler !== null
                    || $this->terminalReleaseHandler !== null,
        ];
    }

    /**
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function onRequest(string $method, callable $handler): void
    {
        $this->requestHandlers[$method][] = $handler;
    }

    /**
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function offRequest(string $method, callable $handler): void
    {
        if (!array_key_exists($method, $this->requestHandlers)) {
            return;
        }

        $this->requestHandlers[$method] = array_values(array_filter(
            $this->requestHandlers[$method],
            static fn(callable $existing): bool => $existing !== $handler,
        ));
    }

    /**
     * @param callable(string, array<string, mixed>): mixed $handler
     */
    public function onAnyRequest(callable $handler): void
    {
        $this->anyRequestHandler = $handler;
    }

    /**
     * @param callable(string, array<string, mixed>): mixed $handler
     */
    public function offAnyRequest(callable $handler): void
    {
        if ($this->anyRequestHandler === $handler) {
            $this->anyRequestHandler = null;
        }
    }

    /**
     * @param callable(RequestPermission): (RequestPermissionOutcome|array<string, mixed>) $handler
     */
    public function onRequestPermission(callable $handler): void
    {
        $this->requestPermissionHandler = $handler;
    }

    /**
     * @param callable(RequestPermission): (RequestPermissionOutcome|array<string, mixed>) $handler
     */
    public function offRequestPermission(callable $handler): void
    {
        if ($this->requestPermissionHandler === $handler) {
            $this->requestPermissionHandler = null;
        }
    }

    /**
     * @param callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler
     */
    public function onReadTextFile(callable $handler): void
    {
        $this->readTextFileHandler = $handler;
    }

    /**
     * @param callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler
     */
    public function offReadTextFile(callable $handler): void
    {
        if ($this->readTextFileHandler === $handler) {
            $this->readTextFileHandler = null;
        }
    }

    /**
     * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
     */
    public function onWriteTextFile(callable $handler): void
    {
        $this->writeTextFileHandler = $handler;
    }

    /**
     * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
     */
    public function offWriteTextFile(callable $handler): void
    {
        if ($this->writeTextFileHandler === $handler) {
            $this->writeTextFileHandler = null;
        }
    }

    /**
     * @param callable(TerminalCreateRequest): (TerminalCreateResult|array<string, mixed>) $handler
     */
    public function onTerminalCreate(callable $handler): void
    {
        $this->terminalCreateHandler = $handler;
    }

    /**
     * @param callable(TerminalCreateRequest): (TerminalCreateResult|array<string, mixed>) $handler
     */
    public function offTerminalCreate(callable $handler): void
    {
        if ($this->terminalCreateHandler === $handler) {
            $this->terminalCreateHandler = null;
        }
    }

    /**
     * @param callable(TerminalOutputRequest): (TerminalOutputResult|array<string, mixed>) $handler
     */
    public function onTerminalOutput(callable $handler): void
    {
        $this->terminalOutputHandler = $handler;
    }

    /**
     * @param callable(TerminalOutputRequest): (TerminalOutputResult|array<string, mixed>) $handler
     */
    public function offTerminalOutput(callable $handler): void
    {
        if ($this->terminalOutputHandler === $handler) {
            $this->terminalOutputHandler = null;
        }
    }

    /**
     * @param callable(TerminalWaitForExitRequest): (TerminalWaitForExitResult|array<string, mixed>) $handler
     */
    public function onTerminalWaitForExit(callable $handler): void
    {
        $this->terminalWaitForExitHandler = $handler;
    }

    /**
     * @param callable(TerminalWaitForExitRequest): (TerminalWaitForExitResult|array<string, mixed>) $handler
     */
    public function offTerminalWaitForExit(callable $handler): void
    {
        if ($this->terminalWaitForExitHandler === $handler) {
            $this->terminalWaitForExitHandler = null;
        }
    }

    /**
     * @param callable(TerminalKillRequest): (array<string, mixed>|null) $handler
     */
    public function onTerminalKill(callable $handler): void
    {
        $this->terminalKillHandler = $handler;
    }

    /**
     * @param callable(TerminalKillRequest): (array<string, mixed>|null) $handler
     */
    public function offTerminalKill(callable $handler): void
    {
        if ($this->terminalKillHandler === $handler) {
            $this->terminalKillHandler = null;
        }
    }

    /**
     * @param callable(TerminalReleaseRequest): (array<string, mixed>|null) $handler
     */
    public function onTerminalRelease(callable $handler): void
    {
        $this->terminalReleaseHandler = $handler;
    }

    /**
     * @param callable(TerminalReleaseRequest): (array<string, mixed>|null) $handler
     */
    public function offTerminalRelease(callable $handler): void
    {
        if ($this->terminalReleaseHandler === $handler) {
            $this->terminalReleaseHandler = null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(int|string, mixed): void $sendResponse
     * @param callable(int|string, int, string, mixed=): void $sendError
     */
    public function handleServerRequest(array $data, callable $sendResponse, callable $sendError): void
    {
        $id = $data['id'];
        if (!is_int($id) && !is_string($id)) {
            return;
        }

        $method = $data['method'];
        if (!is_string($method)) {
            $message = 'Invalid Request';
            $sendError($id, -32_600, $message);
            return;
        }

        if ($this->dispatchTypedRequest($method, $id, $data, $sendResponse, $sendError)) {
            return;
        }

        $methodHandler = $this->requestHandlers[$method][0] ?? null;
        $anyHandler = $this->anyRequestHandler;

        if ($methodHandler === null && $anyHandler === null) {
            $sendError($id, -32_601, "Method not found: {$method}");
            return;
        }

        $params = $this->objectParams($data);

        try {
            $result = $methodHandler !== null ? $methodHandler($params) : $anyHandler($method, $params);
            $sendResponse($id, $result);
        } catch (Throwable $e) {
            $sendError($id, -32_603, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(int|string, mixed): void $sendResponse
     * @param callable(int|string, int, string, mixed=): void $sendError
     */
    private function dispatchTypedRequest(
        string $method,
        int|string $id,
        array $data,
        callable $sendResponse,
        callable $sendError,
    ): bool {
        if ($method === 'session/request_permission' && $this->requestPermissionHandler !== null) {
            $this->handleRequestPermission($id, $data, $sendResponse, $sendError);
            return true;
        }

        if ($method === 'fs/read_text_file' && $this->readTextFileHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'fs/read_text_file',
                ReadTextFileRequest::fromArray(...),
                $this->readTextFileHandler,
                $this->normalizeReadTextFileResult(...),
            );
            return true;
        }

        if ($method === 'fs/write_text_file' && $this->writeTextFileHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'fs/write_text_file',
                WriteTextFileRequest::fromArray(...),
                $this->writeTextFileHandler,
                $this->normalizeWriteTextFileResult(...),
            );
            return true;
        }

        if ($method === 'terminal/create' && $this->terminalCreateHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'terminal/create',
                TerminalCreateRequest::fromArray(...),
                $this->terminalCreateHandler,
                $this->normalizeTerminalCreateResult(...),
            );
            return true;
        }

        if ($method === 'terminal/output' && $this->terminalOutputHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'terminal/output',
                TerminalOutputRequest::fromArray(...),
                $this->terminalOutputHandler,
                $this->normalizeTerminalOutputResult(...),
            );
            return true;
        }

        if ($method === 'terminal/wait_for_exit' && $this->terminalWaitForExitHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'terminal/wait_for_exit',
                TerminalWaitForExitRequest::fromArray(...),
                $this->terminalWaitForExitHandler,
                $this->normalizeTerminalWaitForExitResult(...),
            );
            return true;
        }

        if ($method === 'terminal/kill' && $this->terminalKillHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'terminal/kill',
                TerminalKillRequest::fromArray(...),
                $this->terminalKillHandler,
                $this->normalizeTerminalVoidResult(...),
            );
            return true;
        }

        if ($method === 'terminal/release' && $this->terminalReleaseHandler !== null) {
            $this->executeTypedRequest(
                $id,
                $data,
                $sendResponse,
                $sendError,
                'terminal/release',
                TerminalReleaseRequest::fromArray(...),
                $this->terminalReleaseHandler,
                $this->normalizeTerminalVoidResult(...),
            );
            return true;
        }

        return false;
    }

    /**
     * @template TRequest of object
     * @template TResult
     * @param array<string, mixed> $data
     * @param callable(int|string, mixed): void $sendResponse
     * @param callable(int|string, int, string, mixed=): void $sendError
     * @param callable(array<string, mixed>): TRequest $parse
     * @param (callable(TRequest): TResult)|null $handler
     * @param callable(TResult): mixed $normalize
     */
    private function executeTypedRequest(
        int|string $id,
        array $data,
        callable $sendResponse,
        callable $sendError,
        string $method,
        callable $parse,
        ?callable $handler,
        callable $normalize,
    ): void {
        try {
            $request = $parse($this->objectParams($data));
        } catch (Throwable $e) {
            $sendError($id, -32_602, $e->getMessage());
            return;
        }

        try {
            if ($handler === null) {
                $sendError($id, -32_601, "Method not found: {$method}");
                return;
            }

            $sendResponse($id, $normalize($handler($request)));
        } catch (Throwable $e) {
            $sendError($id, -32_603, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(int|string, mixed): void $sendResponse
     * @param callable(int|string, int, string, mixed=): void $sendError
     */
    private function handleRequestPermission(
        int|string $id,
        array $data,
        callable $sendResponse,
        callable $sendError,
    ): void {
        try {
            $request = RequestPermission::fromArray($this->objectParams($data));
        } catch (Throwable $e) {
            $sendError($id, -32_602, $e->getMessage());
            return;
        }

        $key = $this->responseKey($id);
        $this->pendingPermissionRequests[$key] = [
            'id' => $id,
            'sessionId' => $request->getSessionId(),
            'sendResponse' => $sendResponse,
        ];

        try {
            $handler = $this->requestPermissionHandler;
            if (!is_callable($handler)) {
                $message = 'Method not found: session/request_permission';
                $sendError($id, -32_601, $message);
                return;
            }

            $result = $handler($request);
            if (!array_key_exists($key, $this->pendingPermissionRequests)) {
                return;
            }

            unset($this->pendingPermissionRequests[$key]);
            $sendResponse($id, $this->normalizeRequestPermissionResult($result));
        } catch (Throwable $e) {
            if (!array_key_exists($key, $this->pendingPermissionRequests)) {
                return;
            }

            unset($this->pendingPermissionRequests[$key]);
            $sendError($id, -32_603, $e->getMessage());
        }
    }

    /**
     * @param RequestPermissionOutcome|array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeRequestPermissionResult(RequestPermissionOutcome|array $result): array
    {
        if ($result instanceof RequestPermissionOutcome) {
            return $result->toResultArray();
        }

        return $result;
    }

    /**
     * @param ReadTextFileResult|string|array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeReadTextFileResult(ReadTextFileResult|string|array $result): array
    {
        if ($result instanceof ReadTextFileResult) {
            return $result->toResultArray();
        }

        if (is_string($result)) {
            return ['content' => $result];
        }

        return $result;
    }

    /**
     * @param WriteTextFileResult|array<string, mixed>|null $result
     * @return array<string, mixed>|null
     */
    private function normalizeWriteTextFileResult(WriteTextFileResult|array|null $result): ?array
    {
        if ($result instanceof WriteTextFileResult) {
            return $result->toResultArray();
        }

        return $result;
    }

    /**
     * @param TerminalCreateResult|array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeTerminalCreateResult(TerminalCreateResult|array $result): array
    {
        if ($result instanceof TerminalCreateResult) {
            return $result->toResultArray();
        }

        return $result;
    }

    /**
     * @param TerminalOutputResult|array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeTerminalOutputResult(TerminalOutputResult|array $result): array
    {
        if ($result instanceof TerminalOutputResult) {
            return $result->toResultArray();
        }

        return $result;
    }

    /**
     * @param TerminalWaitForExitResult|array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeTerminalWaitForExitResult(TerminalWaitForExitResult|array $result): array
    {
        if ($result instanceof TerminalWaitForExitResult) {
            return $result->toResultArray();
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $result
     * @return array<string, mixed>|null
     */
    private function normalizeTerminalVoidResult(?array $result): ?array
    {
        return $result;
    }

    public function cancelPendingPermissionRequests(string $sessionId): void
    {
        foreach ($this->pendingPermissionRequests as $key => $request) {
            if ($request['sessionId'] !== $sessionId) {
                continue;
            }

            $request['sendResponse']($request['id'], RequestPermissionOutcome::cancelled()->toResultArray());
            unset($this->pendingPermissionRequests[$key]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function objectParams(array $data): array
    {
        $params = $data['params'] ?? [];
        if (!is_array($params) || array_is_list($params)) {
            return [];
        }

        /** @var array<string, mixed> $params */
        return $params;
    }

    private function responseKey(int|string $id): string
    {
        return gettype($id) . ':' . $id;
    }
}
