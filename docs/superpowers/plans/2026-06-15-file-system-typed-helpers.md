# File System Typed Helpers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add typed DTOs and handler helpers for ACP `fs/read_text_file` and `fs/write_text_file` agent-to-client requests.

**Architecture:** Four small DTOs in `src/Dto/FileSystem/` model request/result shapes. `Client` gains `onReadTextFile()` / `onWriteTextFile()` registration methods and dispatches to typed handlers before falling back to generic `onRequest()`. Handler return values are normalized to the JSON-RPC result shape expected by the protocol.

**Tech Stack:** PHP 8.1+, PHPUnit, Mago (lint/analyze), Composer PSR-4 autoloading.

---

## File map

| File | Responsibility |
|------|----------------|
| `src/Dto/FileSystem/ReadTextFileRequest.php` | Typed params for `fs/read_text_file` |
| `src/Dto/FileSystem/ReadTextFileResult.php` | Typed result for `fs/read_text_file` |
| `src/Dto/FileSystem/WriteTextFileRequest.php` | Typed params for `fs/write_text_file` |
| `src/Dto/FileSystem/WriteTextFileResult.php` | Typed empty result for `fs/write_text_file` |
| `src/Client.php` | Handler registration, dispatch, return normalization |
| `tests/Dto/FileSystem/ReadTextFileRequestTest.php` | DTO parsing tests |
| `tests/Dto/FileSystem/ReadTextFileResultTest.php` | Result serialization tests |
| `tests/Dto/FileSystem/WriteTextFileRequestTest.php` | DTO parsing tests |
| `tests/Dto/FileSystem/WriteTextFileResultTest.php` | Result serialization tests |
| `tests/ClientTest.php` | Integration tests for typed handlers |

---

### Task 1: Create `ReadTextFileRequest` DTO

**Files:**
- Create: `src/Dto/FileSystem/ReadTextFileRequest.php`
- Test: `tests/Dto/FileSystem/ReadTextFileRequestTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Dto/FileSystem/ReadTextFileRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class ReadTextFileRequestTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $request = ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'line' => 10,
            'limit' => 50,
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('/repo/a.php', $request->getPath());
        static::assertSame(10, $request->getLine());
        static::assertSame(50, $request->getLimit());
    }

    public function testDefaultsOptionalFieldsToNull(): void
    {
        $request = ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ]);

        static::assertNull($request->getLine());
        static::assertNull($request->getLimit());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: sessionId must be a string');

        ReadTextFileRequest::fromArray(['path' => '/repo/a.php']);
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: path must be a string');

        ReadTextFileRequest::fromArray(['sessionId' => 'sess_1']);
    }

    public function testRejectsInvalidLineType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: line must be an integer');

        ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'line' => 'ten',
        ]);
    }

    public function testRejectsInvalidLimitType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/read_text_file params: limit must be an integer');

        ReadTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'limit' => 'fifty',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/ReadTextFileRequestTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Dto/FileSystem/ReadTextFileRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

use Yankewei\AcpClient\Util\Assert;

final class ReadTextFileRequest
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $path,
        private readonly ?int $line = null,
        private readonly ?int $limit = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Yankewei\AcpClient\Exception\AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Assert::requiredString(
                $data,
                'sessionId',
                'Invalid fs/read_text_file params: sessionId must be a string',
            ),
            Assert::requiredString(
                $data,
                'path',
                'Invalid fs/read_text_file params: path must be a string',
            ),
            Assert::optionalInt(
                $data['line'] ?? null,
                'Invalid fs/read_text_file params: line must be an integer',
            ),
            Assert::optionalInt(
                $data['limit'] ?? null,
                'Invalid fs/read_text_file params: limit must be an integer',
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/ReadTextFileRequestTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dto/FileSystem/ReadTextFileRequest.php tests/Dto/FileSystem/ReadTextFileRequestTest.php
git commit -m "Add ReadTextFileRequest DTO"
```

---

### Task 2: Create `ReadTextFileResult` DTO

**Files:**
- Create: `src/Dto/FileSystem/ReadTextFileResult.php`
- Test: `tests/Dto/FileSystem/ReadTextFileResultTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Dto/FileSystem/ReadTextFileResultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;

final class ReadTextFileResultTest extends TestCase
{
    public function testSerializesToResultArray(): void
    {
        $result = new ReadTextFileResult('hello world');

        static::assertSame(['content' => 'hello world'], $result->toResultArray());
    }

    public function testFromStringFactory(): void
    {
        $result = ReadTextFileResult::fromString('file contents');

        static::assertSame('file contents', $result->getContent());
        static::assertSame(['content' => 'file contents'], $result->toResultArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/ReadTextFileResultTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Dto/FileSystem/ReadTextFileResult.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

final class ReadTextFileResult
{
    public function __construct(
        private readonly string $content,
    ) {}

    public static function fromString(string $content): self
    {
        return new self($content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array{content: string}
     */
    public function toResultArray(): array
    {
        return ['content' => $this->content];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/ReadTextFileResultTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dto/FileSystem/ReadTextFileResult.php tests/Dto/FileSystem/ReadTextFileResultTest.php
git commit -m "Add ReadTextFileResult DTO"
```

---

### Task 3: Create `WriteTextFileRequest` DTO

**Files:**
- Create: `src/Dto/FileSystem/WriteTextFileRequest.php`
- Test: `tests/Dto/FileSystem/WriteTextFileRequestTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Dto/FileSystem/WriteTextFileRequestTest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Exception\AcpException;

final class WriteTextFileRequestTest extends TestCase
{
    public function testParsesAllFields(): void
    {
        $request = WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'content' => '<?php echo "hi";',
        ]);

        static::assertSame('sess_1', $request->getSessionId());
        static::assertSame('/repo/a.php', $request->getPath());
        static::assertSame('<?php echo "hi";', $request->getContent());
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: sessionId must be a string');

        WriteTextFileRequest::fromArray([
            'path' => '/repo/a.php',
            'content' => 'x',
        ]);
    }

    public function testRejectsMissingPath(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: path must be a string');

        WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'content' => 'x',
        ]);
    }

    public function testRejectsMissingContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid fs/write_text_file params: content must be a string');

        WriteTextFileRequest::fromArray([
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/WriteTextFileRequestTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Dto/FileSystem/WriteTextFileRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

use Yankewei\AcpClient\Util\Assert;

final class WriteTextFileRequest
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $path,
        private readonly string $content,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Yankewei\AcpClient\Exception\AcpException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Assert::requiredString(
                $data,
                'sessionId',
                'Invalid fs/write_text_file params: sessionId must be a string',
            ),
            Assert::requiredString(
                $data,
                'path',
                'Invalid fs/write_text_file params: path must be a string',
            ),
            Assert::requiredString(
                $data,
                'content',
                'Invalid fs/write_text_file params: content must be a string',
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/WriteTextFileRequestTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dto/FileSystem/WriteTextFileRequest.php tests/Dto/FileSystem/WriteTextFileRequestTest.php
git commit -m "Add WriteTextFileRequest DTO"
```

---

### Task 4: Create `WriteTextFileResult` DTO

**Files:**
- Create: `src/Dto/FileSystem/WriteTextFileResult.php`
- Test: `tests/Dto/FileSystem/WriteTextFileResultTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Dto/FileSystem/WriteTextFileResultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Dto\FileSystem;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;

final class WriteTextFileResultTest extends TestCase
{
    public function testSerializesToEmptyResultArray(): void
    {
        $result = new WriteTextFileResult();

        static::assertSame([], $result->toResultArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/WriteTextFileResultTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Create `src/Dto/FileSystem/WriteTextFileResult.php`:

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Dto\FileSystem;

final class WriteTextFileResult
{
    /**
     * @return array{}
     */
    public function toResultArray(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/phpunit tests/Dto/FileSystem/WriteTextFileResultTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Dto/FileSystem/WriteTextFileResult.php tests/Dto/FileSystem/WriteTextFileResultTest.php
git commit -m "Add WriteTextFileResult DTO"
```

---

### Task 5: Add `Client` typed handlers and dispatch

**Files:**
- Modify: `src/Client.php`

- [ ] **Step 1: Add imports and handler properties**

At the top of `src/Client.php`, add imports after the existing Dto imports:

```php
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;
```

Add private properties after `$requestPermissionHandler`:

```php
/** @var (callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>))|null */
private $readTextFileHandler = null;

/** @var (callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null))|null */
private $writeTextFileHandler = null;
```

- [ ] **Step 2: Add registration methods**

Add after `offRequestPermission()`:

```php
/**
 * Register a typed handler for ACP fs/read_text_file requests.
 *
 * The handler may return ReadTextFileResult, a plain string (wrapped as
 * {content: ...}), or a raw result array for agent-specific extensions.
 *
 * Setting a new handler replaces any previously registered handler.
 *
 * @param callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler
 */
public function onReadTextFile(callable $handler): void
{
    $this->readTextFileHandler = $handler;
}

/**
 * Remove the typed fs/read_text_file handler only if it is currently registered.
 *
 * @param callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler
 */
public function offReadTextFile(callable $handler): void
{
    if ($this->readTextFileHandler === $handler) {
        $this->readTextFileHandler = null;
    }
}

/**
 * Register a typed handler for ACP fs/write_text_file requests.
 *
 * The handler may return WriteTextFileResult, null/void, or a raw result array
 * for agent-specific extensions.
 *
 * Setting a new handler replaces any previously registered handler.
 *
 * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
 */
public function onWriteTextFile(callable $handler): void
{
    $this->writeTextFileHandler = $handler;
}

/**
 * Remove the typed fs/write_text_file handler only if it is currently registered.
 *
 * @param callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler
 */
public function offWriteTextFile(callable $handler): void
{
    if ($this->writeTextFileHandler === $handler) {
        $this->writeTextFileHandler = null;
    }
}
```

- [ ] **Step 3: Add dispatch branches**

In `handleServerRequest()`, after the `session/request_permission` branch and before the generic handler lookup, add:

```php
if ($method === 'fs/read_text_file' && $this->readTextFileHandler !== null) {
    $this->handleReadTextFile($id, $data);
    return;
}

if ($method === 'fs/write_text_file' && $this->writeTextFileHandler !== null) {
    $this->handleWriteTextFile($id, $data);
    return;
}
```

- [ ] **Step 4: Add handler methods**

Add after `normalizeRequestPermissionResult()`:

```php
/**
 * @param array<string, mixed> $data
 */
private function handleReadTextFile(int|string $id, array $data): void
{
    $params = $data['params'] ?? [];
    if (!is_array($params) || array_is_list($params)) {
        $params = [];
    }

    try {
        /** @var array<string, mixed> $params */
        $request = ReadTextFileRequest::fromArray($params);
    } catch (Throwable $e) {
        $this->sendError($id, -32_602, $e->getMessage());
        return;
    }

    try {
        $handler = $this->readTextFileHandler;
        if ($handler === null) {
            $this->sendError($id, -32_601, 'Method not found: fs/read_text_file');
            return;
        }

        $result = $handler($request);
        $this->sendResponse($id, $this->normalizeReadTextFileResult($result));
    } catch (Throwable $e) {
        $this->sendError($id, -32_603, $e->getMessage());
    }
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
 * @param array<string, mixed> $data
 */
private function handleWriteTextFile(int|string $id, array $data): void
{
    $params = $data['params'] ?? [];
    if (!is_array($params) || array_is_list($params)) {
        $params = [];
    }

    try {
        /** @var array<string, mixed> $params */
        $request = WriteTextFileRequest::fromArray($params);
    } catch (Throwable $e) {
        $this->sendError($id, -32_602, $e->getMessage());
        return;
    }

    try {
        $handler = $this->writeTextFileHandler;
        if ($handler === null) {
            $this->sendError($id, -32_601, 'Method not found: fs/write_text_file');
            return;
        }

        $result = $handler($request);
        $this->sendResponse($id, $this->normalizeWriteTextFileResult($result));
    } catch (Throwable $e) {
        $this->sendError($id, -32_603, $e->getMessage());
    }
}

/**
 * @param WriteTextFileResult|array<string, mixed>|null $result
 * @return array<string, mixed>
 */
private function normalizeWriteTextFileResult(WriteTextFileResult|array|null $result): array
{
    if ($result instanceof WriteTextFileResult) {
        return $result->toResultArray();
    }

    if (is_array($result)) {
        return $result;
    }

    return [];
}
```

- [ ] **Step 5: Run existing tests to verify no regressions**

```bash
vendor/bin/phpunit
```

Expected: all existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add src/Client.php
git commit -m "Add typed fs/read_text_file and fs/write_text_file handlers to Client"
```

---

### Task 6: Add `Client` integration tests

**Files:**
- Modify: `tests/ClientTest.php`

- [ ] **Step 1: Add imports**

At the top of `tests/ClientTest.php`, add:

```php
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest;
use Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult;
```

- [ ] **Step 2: Add tests before the private helper methods at the end**

Add these test methods:

```php
public function testOnReadTextFileRespondsWithResultDto(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/read_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'line' => 10,
            'limit' => 50,
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onReadTextFile(static function (ReadTextFileRequest $request): ReadTextFileResult {
        self::assertSame('sess_1', $request->getSessionId());
        self::assertSame('/repo/a.php', $request->getPath());
        self::assertSame(10, $request->getLine());
        self::assertSame(50, $request->getLimit());

        return new ReadTextFileResult('contents');
    });

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame('fs-1', $response['id']);
    static::assertArrayNotHasKey('error', $response);
    static::assertSame(['content' => 'contents'], $response['result']);
}

public function testOnReadTextFileWrapsStringResult(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/read_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onReadTextFile(static fn(): string => 'plain contents');

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame(['content' => 'plain contents'], $response['result']);
}

public function testOnReadTextFileReturnsInvalidParamsForMalformedRequest(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/read_text_file',
        'params' => ['sessionId' => 'sess_1'],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onReadTextFile(static fn(): string => 'ignored');

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame(-32_602, self::errorOf($response)['code']);
}

public function testOnWriteTextFileRespondsWithEmptyResult(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/write_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'content' => 'hello',
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onWriteTextFile(static function (WriteTextFileRequest $request): void {
        self::assertSame('sess_1', $request->getSessionId());
        self::assertSame('/repo/a.php', $request->getPath());
        self::assertSame('hello', $request->getContent());
    });

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame('fs-1', $response['id']);
    static::assertArrayNotHasKey('error', $response);
    static::assertSame([], $response['result']);
}

public function testOnWriteTextFileRespondsWithResultDto(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/write_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
            'content' => 'hello',
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onWriteTextFile(static fn(): WriteTextFileResult => new WriteTextFileResult());

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame([], $response['result']);
}

public function testReadTextFileHandlerTakesPrecedenceOverGenericHandler(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/read_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $client->onRequest('fs/read_text_file', static fn(): string => 'generic');
    $client->onReadTextFile(static fn(): ReadTextFileResult => new ReadTextFileResult('typed'));

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame(['content' => 'typed'], $response['result']);
}

public function testReadTextFileHandlerCanBeRemoved(): void
{
    $transport = new FakeTransport();
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 'fs-1',
        'method' => 'fs/read_text_file',
        'params' => [
            'sessionId' => 'sess_1',
            'path' => '/repo/a.php',
        ],
    ]);
    $transport->responses[] = self::encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => ['ok' => true],
    ]);

    $client = new Client($transport, 1.0, false);
    $handler = static fn(): ReadTextFileResult => new ReadTextFileResult('contents');
    $client->onReadTextFile($handler);
    $client->offReadTextFile($handler);

    static::assertSame(['ok' => true], $client->call('initialize'));

    $response = self::decode($transport->sent[1]);
    static::assertSame(-32_601, self::errorOf($response)['code']);
}
```

- [ ] **Step 3: Run the new tests**

```bash
vendor/bin/phpunit tests/ClientTest.php --filter 'ReadTextFile|WriteTextFile'
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/ClientTest.php
git commit -m "Add Client tests for typed file system handlers"
```

---

### Task 7: Quality gate

- [ ] **Step 1: Run full test suite**

```bash
vendor/bin/phpunit
```

Expected: PASS (all tests).

- [ ] **Step 2: Run static analysis**

```bash
vendor/bin/mago analyze
```

Expected: no errors.

- [ ] **Step 3: Run linter**

```bash
vendor/bin/mago lint
```

Expected: no violations (or only pre-existing baseline issues).

- [ ] **Step 4: Optional auto-format**

```bash
vendor/bin/mago format
```

If the formatter changes files, review and commit them:

```bash
git add -A
git commit -m "Apply mago formatting"
```

---

## Self-review checklist

- [ ] `ReadTextFileRequest` validates `sessionId`, `path`, `line`, `limit`.
- [ ] `ReadTextFileResult` serializes to `['content' => string]`.
- [ ] `WriteTextFileRequest` validates `sessionId`, `path`, `content`.
- [ ] `WriteTextFileResult` serializes to `[]`.
- [ ] `Client::onReadTextFile()` / `onWriteTextFile()` register handlers.
- [ ] `Client::offReadTextFile()` / `offWriteTextFile()` remove handlers.
- [ ] Typed handlers dispatch before generic `onRequest()` handlers.
- [ ] String results from read handlers are wrapped as `['content' => ...]`.
- [ ] Null/void results from write handlers produce empty JSON-RPC result.
- [ ] Malformed params produce `-32602`.
- [ ] Handler exceptions produce `-32603`.
- [ ] Full PHPUnit, `mago analyze`, and `mago lint` pass.
