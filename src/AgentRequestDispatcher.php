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
    /**
     * Generic per-method handlers. A JSON-RPC request needs exactly one
     * response, so each method keeps a single handler; registering again
     * overwrites the previous one. This mirrors the typed and permission
     * handler slots and avoids the previous "append but only invoke the
     * first" silent drop.
     *
     * @var array<string, callable(array<string, mixed>): mixed>
     */
    private array $genericHandlers = [];

    /** @var (callable(string, array<string, mixed>): mixed)|null */
    private mixed $anyRequestHandler = null;

    /** @var (callable(RequestPermission): (RequestPermissionOutcome|array<string, mixed>))|null */
    private mixed $requestPermissionHandler = null;

    /** @var array<string, TypedRequestSpec> */
    private array $typedSpecs;

    /** @var array<string, callable> */
    private array $typedHandlers = [];

    /** @var array<string, array{id: int|string, sessionId: string, sendResponse: callable(int|string, mixed): void}> */
    private array $pendingPermissionRequests = [];

    public function __construct()
    {
        $this->typedSpecs = [
            'fs/read_text_file' => new TypedRequestSpec(
                ReadTextFileRequest::fromArray(...),
                $this->normalizeReadTextFileResult(...),
                'fs',
                'readTextFile',
            ),
            'fs/write_text_file' => new TypedRequestSpec(
                WriteTextFileRequest::fromArray(...),
                $this->normalizeWriteTextFileResult(...),
                'fs',
                'writeTextFile',
            ),
            'terminal/create' => new TypedRequestSpec(
                TerminalCreateRequest::fromArray(...),
                $this->normalizeTerminalCreateResult(...),
                'terminal',
                null,
            ),
            'terminal/output' => new TypedRequestSpec(
                TerminalOutputRequest::fromArray(...),
                $this->normalizeTerminalOutputResult(...),
                'terminal',
                null,
            ),
            'terminal/wait_for_exit' => new TypedRequestSpec(
                TerminalWaitForExitRequest::fromArray(...),
                $this->normalizeTerminalWaitForExitResult(...),
                'terminal',
                null,
            ),
            'terminal/kill' => new TypedRequestSpec(
                TerminalKillRequest::fromArray(...),
                $this->normalizeTerminalVoidResult(...),
                'terminal',
                null,
            ),
            'terminal/release' => new TypedRequestSpec(
                TerminalReleaseRequest::fromArray(...),
                $this->normalizeTerminalVoidResult(...),
                'terminal',
                null,
            ),
        ];
    }

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
                'readTextFile' => $this->hasHandlerFor('fs/read_text_file'),
                'writeTextFile' => $this->hasHandlerFor('fs/write_text_file'),
            ],
            'terminal' =>
                $this->hasHandlerFor('terminal/create')
                    || $this->hasHandlerFor('terminal/output')
                    || $this->hasHandlerFor('terminal/wait_for_exit')
                    || $this->hasHandlerFor('terminal/kill')
                    || $this->hasHandlerFor('terminal/release'),
        ];
    }

    /**
     * Register a handler for a method-specific agent request.
     *
     * A JSON-RPC request yields exactly one response, so only one handler per
     * method is kept; a later registration for the same method overwrites the
     * previous one. For the standard fs/terminal methods, registering via
     * onRequest() also advertises the matching client capability in
     * initialize(), so the agent will route those requests to this handler.
     *
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function onRequest(string $method, callable $handler): void
    {
        $this->genericHandlers[$method] = $handler;
    }

    /**
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function offRequest(string $method, callable $handler): void
    {
        if (($this->genericHandlers[$method] ?? null) === $handler) {
            unset($this->genericHandlers[$method]);
        }
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
        $this->typedHandlers['fs/read_text_file'] = $handler;
    }

    /**
     * @param callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler
     */
    public function offReadTextFile(callable $handler): void
    {
        $this->offTypedHandler('fs/read_text_file', $handler);
    }

    /**
     * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
     */
    public function onWriteTextFile(callable $handler): void
    {
        $this->typedHandlers['fs/write_text_file'] = $handler;
    }

    /**
     * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
     */
    public function offWriteTextFile(callable $handler): void
    {
        $this->offTypedHandler('fs/write_text_file', $handler);
    }

    /**
     * @param callable(TerminalCreateRequest): (TerminalCreateResult|array<string, mixed>) $handler
     */
    public function onTerminalCreate(callable $handler): void
    {
        $this->typedHandlers['terminal/create'] = $handler;
    }

    /**
     * @param callable(TerminalCreateRequest): (TerminalCreateResult|array<string, mixed>) $handler
     */
    public function offTerminalCreate(callable $handler): void
    {
        $this->offTypedHandler('terminal/create', $handler);
    }

    /**
     * @param callable(TerminalOutputRequest): (TerminalOutputResult|array<string, mixed>) $handler
     */
    public function onTerminalOutput(callable $handler): void
    {
        $this->typedHandlers['terminal/output'] = $handler;
    }

    /**
     * @param callable(TerminalOutputRequest): (TerminalOutputResult|array<string, mixed>) $handler
     */
    public function offTerminalOutput(callable $handler): void
    {
        $this->offTypedHandler('terminal/output', $handler);
    }

    /**
     * @param callable(TerminalWaitForExitRequest): (TerminalWaitForExitResult|array<string, mixed>) $handler
     */
    public function onTerminalWaitForExit(callable $handler): void
    {
        $this->typedHandlers['terminal/wait_for_exit'] = $handler;
    }

    /**
     * @param callable(TerminalWaitForExitRequest): (TerminalWaitForExitResult|array<string, mixed>) $handler
     */
    public function offTerminalWaitForExit(callable $handler): void
    {
        $this->offTypedHandler('terminal/wait_for_exit', $handler);
    }

    /**
     * @param callable(TerminalKillRequest): (array<string, mixed>|null) $handler
     */
    public function onTerminalKill(callable $handler): void
    {
        $this->typedHandlers['terminal/kill'] = $handler;
    }

    /**
     * @param callable(TerminalKillRequest): (array<string, mixed>|null) $handler
     */
    public function offTerminalKill(callable $handler): void
    {
        $this->offTypedHandler('terminal/kill', $handler);
    }

    /**
     * @param callable(TerminalReleaseRequest): (array<string, mixed>|null) $handler
     */
    public function onTerminalRelease(callable $handler): void
    {
        $this->typedHandlers['terminal/release'] = $handler;
    }

    /**
     * @param callable(TerminalReleaseRequest): (array<string, mixed>|null) $handler
     */
    public function offTerminalRelease(callable $handler): void
    {
        $this->offTypedHandler('terminal/release', $handler);
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

        if ($method === 'session/request_permission' && $this->requestPermissionHandler !== null) {
            $this->handleRequestPermission($id, $data, $sendResponse, $sendError);
            return;
        }

        if (array_key_exists($method, $this->typedSpecs) && array_key_exists($method, $this->typedHandlers)) {
            $this->executeTypedRequest($method, $id, $data, $sendResponse, $sendError);
            return;
        }

        $genericHandler = $this->genericHandlers[$method] ?? null;
        $anyHandler = $this->anyRequestHandler;

        if ($genericHandler === null && $anyHandler === null) {
            $sendError($id, -32_601, "Method not found: {$method}");
            return;
        }

        $params = $this->objectParams($data);

        try {
            $result = $genericHandler !== null ? $genericHandler($params) : $anyHandler($method, $params);
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
    private function executeTypedRequest(
        string $method,
        int|string $id,
        array $data,
        callable $sendResponse,
        callable $sendError,
    ): void {
        $spec = $this->typedSpecs[$method];
        $handler = $this->typedHandlers[$method];

        try {
            $request = ($spec->parse)($this->objectParams($data));
        } catch (Throwable $e) {
            $sendError($id, -32_602, $e->getMessage());
            return;
        }

        try {
            $sendResponse($id, ($spec->normalize)($handler($request)));
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

    private function hasHandlerFor(string $method): bool
    {
        return array_key_exists($method, $this->typedHandlers) || array_key_exists($method, $this->genericHandlers);
    }

    /**
     * @param callable $handler typed handler for the method
     */
    private function offTypedHandler(string $method, callable $handler): void
    {
        if (($this->typedHandlers[$method] ?? null) === $handler) {
            unset($this->typedHandlers[$method]);
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
