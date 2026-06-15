# File System Protocol Typed Helpers Design

## Goal

Add typed helpers and DTOs for the ACP File System protocol so consumers can handle `fs/read_text_file` and `fs/write_text_file` agent-to-client requests with type-safe objects instead of raw arrays.

The API should feel consistent with the existing `onRequestPermission()` helper.

## Background

The [ACP File System protocol](https://agentclientprotocol.com/protocol/v1/file-system) defines two agent-to-client methods:

- `fs/read_text_file` — read a text file (optionally starting at `line`, limited to `limit` lines).
- `fs/write_text_file` — write or overwrite a text file.

Currently the library supports these only through the generic `Client::onRequest()` mechanism. This design adds dedicated typed handlers.

## New DTOs

### `Yankewei\AcpClient\Dto\FileSystem\ReadTextFileRequest`

Represents the parameters of an `fs/read_text_file` request.

| Property | Type | Notes |
|----------|------|-------|
| `sessionId` | `string` | Required. |
| `path` | `string` | Required. Absolute path to the file. |
| `line` | `?int` | Optional, 1-based starting line. |
| `limit` | `?int` | Optional maximum number of lines. |

- `fromArray(array<string, mixed>): self` validates field types and throws `AcpException` on malformed input.

### `Yankewei\AcpClient\Dto\FileSystem\ReadTextFileResult`

Represents the result of a successful read.

| Property | Type | Notes |
|----------|------|-------|
| `content` | `string` | File contents. |

- `fromString(string): self` convenience factory.
- `toResultArray(): array{return ['content' => $this->content];}`

### `Yankewei\AcpClient\Dto\FileSystem\WriteTextFileRequest`

Represents the parameters of an `fs/write_text_file` request.

| Property | Type | Notes |
|----------|------|-------|
| `sessionId` | `string` | Required. |
| `path` | `string` | Required. Absolute path to the file. |
| `content` | `string` | Required. Text to write. |

- `fromArray(array<string, mixed>): self` validates field types.

### `Yankewei\AcpClient\Dto\FileSystem\WriteTextFileResult`

Represents the empty success result for `fs/write_text_file`.

- `toResultArray(): array{}` returns an empty object.

## Client API

Add four methods to `Yankewei\AcpClient\Client`:

```php
public function onReadTextFile(
    callable(ReadTextFileRequest): (ReadTextFileResult|string|array<string, mixed>) $handler,
): void;

public function offReadTextFile(callable $handler): void;

public function onWriteTextFile(
    callable(WriteTextFileRequest): (WriteTextFileResult|array<string, mixed>|null) $handler,
): void;

public function offWriteTextFile(callable $handler): void;
```

### Handler return normalization

To keep the API ergonomic while remaining type-safe, the client normalizes handler return values:

| Handler returns | JSON-RPC `result` sent |
|-----------------|------------------------|
| `ReadTextFileResult` | `['content' => $result->getContent()]` |
| `string` | `['content' => $string]` |
| `array<string, mixed>` | the array as-is |
| `WriteTextFileResult` | `{}` |
| `null` / `void` | `{}` |

This mirrors `onRequestPermission()`, which accepts `RequestPermissionOutcome|array<string, mixed>`.

## Internal Changes

### `Client`

- Add private nullable callables:
  - `?callable $readTextFileHandler`
  - `?callable $writeTextFileHandler`
- In `handleServerRequest()`:
  - After the `session/request_permission` branch, check for `fs/read_text_file` and `fs/write_text_file`.
  - Parse params into the appropriate DTO.
  - Invoke the registered typed handler.
  - Normalize the return value and send the JSON-RPC response.
  - If the handler throws, reply with JSON-RPC internal error `-32603`.
- Typed handlers take precedence over generic `onRequest()` handlers for these two methods.
- `offReadTextFile()` / `offWriteTextFile()` remove the handler only if the same callable is currently registered, matching `offRequestPermission()`.

### Error Handling

- Malformed params during DTO parsing: reply with `-32602` (Invalid params) and the `AcpException` message.
- Handler throws: reply with `-32603` (Internal error) and the exception message.
- No handler registered: fall through to generic handler logic, which ultimately replies `-32601` (Method not found) if nothing handles it.

## Testing

Add new test files:

- `tests/Dto/FileSystem/ReadTextFileRequestTest.php`
- `tests/Dto/FileSystem/ReadTextFileResultTest.php`
- `tests/Dto/FileSystem/WriteTextFileRequestTest.php`
- `tests/Dto/FileSystem/WriteTextFileResultTest.php`

Extend `tests/ClientTest.php` with cases for:

- `onReadTextFile()` responds with a `ReadTextFileResult`.
- `onReadTextFile()` responds with a `string` and the client wraps it.
- `onWriteTextFile()` responds with `WriteTextFileResult` / `null` / `void`.
- Handler exceptions produce `-32603`.
- Typed handler takes precedence over generic `onRequest('fs/read_text_file', ...)`.
- `offReadTextFile()` / `offWriteTextFile()` remove the handler.

## Backwards Compatibility

- Existing `onRequest('fs/read_text_file', ...)` and `onRequest('fs/write_text_file', ...)` registrations continue to work.
- Typed handlers have higher precedence than generic handlers.
- Default `initialize()` capabilities remain `fs.readTextFile: false` and `fs.writeTextFile: false`. Consumers must still opt-in via `initialize(['clientCapabilities' => ['fs' => ['readTextFile' => true, 'writeTextFile' => true]]])`.

## Files to Change

- `src/Client.php` — add handler registration methods and dispatch logic.
- `src/Dto/FileSystem/ReadTextFileRequest.php` — new.
- `src/Dto/FileSystem/ReadTextFileResult.php` — new.
- `src/Dto/FileSystem/WriteTextFileRequest.php` — new.
- `src/Dto/FileSystem/WriteTextFileResult.php` — new.
- `tests/ClientTest.php` — add handler tests.
- `tests/Dto/FileSystem/*` — new DTO tests.
