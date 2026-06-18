# Repository Guidelines

## Project Overview

`yankewei/acp-client` is a minimal PHP 8.1 library that implements a client for the [Agent Communication Protocol (ACP)](https://agentclientprotocol.com/). It speaks JSON-RPC 2.0 over stdio, exposes a synchronous blocking API, and provides typed convenience methods for the stable ACP v1 lifecycle.

Key responsibilities of the library:

- Send JSON-RPC requests/notifications and wait for responses.
- Skip server-initiated notifications while waiting for a matching response.
- Parse typed result objects (DTOs) for initialize, session, prompt, permission, and session-update messages.
- Dispatch server-initiated notifications and agent-to-client requests to registered handlers.
- Enforce ACP Session Setup rules when strict protocol mode is enabled.

## Technology Stack

- **Language:** PHP `^8.1`.
- **Package Manager:** Composer (`composer.json`, `composer.lock`).
- **Test Framework:** PHPUnit `^10.0 || ^11.0` (`phpunit.xml`).
- **Static Analysis / Lint / Format:** [Mago](https://mago.carthage.software/) `^1.30.0` (`mago.toml`).
  - `mago analyze` replaces the previous PHPStan step.
  - `mago lint` enforces PER-CS style.
  - `mago format` applies the formatter.
- **CI:** GitHub Actions (`.github/workflows/ci.yml`) tests PHP 8.1–8.4.

> **Note:** `.github/workflows/ci.yml` runs `vendor/bin/mago analyze` and `vendor/bin/mago lint`.

## Project Structure

Source code lives under `src/` in the `Yankewei\AcpClient\` PSR-4 namespace. Tests mirror the source layout under `tests/` in the `Yankewei\AcpClient\Tests\` namespace.

```
src/
├── Client.php                      # Thin facade: wires transport + dispatchers + validator
├── Acp.php                         # Typed ACP v1 lifecycle wrappers (returned by Client::acp())
├── JsonRpcPeer.php                 # Low-level JSON-RPC call/notify + waitForResponse loop (Client::rpc())
├── ProtocolValidator.php           # Strict-mode Session Setup + prompt/MCP validation
├── NotificationDispatcher.php      # Server-initiated notification listener registry (Client::notifications())
├── AgentRequestDispatcher.php      # Agent-to-client request handler registry + capability advertise (Client::requests())
├── TypedRequestSpec.php            # Internal spec pairing a typed request parser with a result normalizer
├── Transport/
│   ├── TransportInterface.php      # Abstraction for transports
│   ├── StdioTransport.php          # stdio process transport with stderr capture (ACP v1 stable)
│   ├── StreamableHttpTransport.php # Draft synchronous batch HTTP transport
│   ├── StreamableHttpClientInterface.php
│   ├── StreamableHttpResponse.php
│   └── NativeStreamableHttpClient.php
├── JsonRpc/
│   ├── Request.php                 # JSON-RPC request builder (id counter)
│   ├── Response.php                # JSON-RPC response parser
│   └── Error.php                   # JSON-RPC error object
├── Dto/                            # Typed value objects for ACP results
│   ├── InitializeResult.php
│   ├── Session.php
│   ├── SessionListResult.php
│   ├── SessionConfigOptionsResult.php
│   ├── SessionInfo.php
│   ├── PromptResult.php
│   ├── RequestPermission.php
│   ├── RequestPermissionOutcome.php
│   ├── PermissionOption.php
│   ├── AuthMethod.php
│   ├── ConfigOption.php
│   ├── ConfigOptionValue.php
│   ├── ToolCallLocation.php
│   ├── DtoHelper.php
│   ├── ContentBlock/               # Prompt content block types and factory
│   ├── ToolCallContent/            # Tool-call content types and factory
│   ├── FileSystem/                 # fs/read_text_file + fs/write_text_file request/result DTOs
│   └── Terminal/                   # terminal/* request/result DTOs (create, output, wait_for_exit, kill, release)
├── Event/                          # Server-initiated notification handling
│   ├── Notification.php
│   ├── SessionInfoUpdate.php
│   ├── SessionInfoUpdateMapper.php
│   └── Update/                     # Typed session/update variants
├── Exception/
│   ├── TransportException.php      # Process/IO/timeout failures
│   ├── JsonRpcException.php        # JSON-RPC error responses
│   └── AcpException.php            # Protocol/validation/parser errors
└── Util/
    ├── Assert.php                  # Reusable input validation helpers
    └── Path.php                    # Absolute-path checks

tests/
├── ClientTest.php                  # Core client behavior, capabilities, strict-mode, typed handlers
├── FakeTransport.php               # In-memory transport for unit tests
├── Fixtures/                       # Stand-in stdio agents used by tests
├── Dto/                            # DTO parsing tests (incl. FileSystem, Terminal)
├── Event/                          # Notification/update tests
├── Exception/                      # Exception tests
├── JsonRpc/                        # JSON-RPC message tests
└── Transport/                      # StreamableHttpTransport tests

examples/
└── kimi-smoke.php                  # Optional live smoke test against `kimi acp`
```

## Build, Test, and Development Commands

Install or refresh dependencies:

```bash
composer update
```

Run the test suite:

```bash
vendor/bin/phpunit
```

Run static analysis and linting (both use baselines in the project root):

```bash
vendor/bin/mago analyze
vendor/bin/mago lint
```

Apply automatic formatting:

```bash
vendor/bin/mago format
```

Optional live smoke test (requires Kimi Code to be installed):

```bash
php examples/kimi-smoke.php
```

## Code Style Guidelines

- Every PHP file starts with `declare(strict_types=1);`.
- Follow PSR-4 autoloading: `src/Dto/Session.php` defines `Yankewei\AcpClient\Dto\Session`; tests mirror source names, e.g. `tests/Dto/SessionTest.php`.
- Prefer `final` classes unless extension is intentionally part of the API. Tests are exempt from the class-finality rule.
- Use typed properties, constructor property promotion, and explicit return types.
- Use PHPDoc array shapes where PHP native types cannot express structure (e.g. `array<string, mixed>`, `array<int, array<string, mixed>>`).
- Keep validation errors specific and actionable; throw `AcpException` for protocol violations.
- Code formatting follows PER-CS defaults via `mago format`.

## Testing Guidelines

- PHPUnit is configured by `phpunit.xml` and discovers all `tests/**/*Test.php` files.
- Test methods are named `test...()`.
- Shared test infrastructure:
  - `tests/FakeTransport.php` — in-memory `TransportInterface` implementation used by most unit tests.
  - `tests/Fixtures/` — small PHP scripts that act as stdio agents for transport-level tests.
- Reset global state where needed (e.g. `ClientTest` resets `Request::$idCounter` via reflection between tests).
- Add or update tests for protocol validation, DTO parsing, transport behavior, and error handling.

## Runtime Architecture

- `Client` is a thin facade and the single entry point. It owns a `TransportInterface`, a default timeout, and a strict-protocol flag. It wires four collaborators and exposes them via accessors:
  - `$client->acp()` — `Acp`: typed ACP v1 lifecycle wrappers returning DTOs.
  - `$client->rpc()` — `JsonRpcPeer`: low-level JSON-RPC `call()` / `notify()` / extension methods.
  - `$client->notifications()` — `NotificationDispatcher`: server-initiated notification listeners.
  - `$client->requests()` — `AgentRequestDispatcher`: agent-to-client request handlers and client-capability advertising.
- Transports are opened lazily on the first `call()` or `notify()`.
- `JsonRpcPeer::call()` sends a JSON-RPC request, then blocks in `waitForResponse()` until the matching response arrives or the timeout expires.
- While waiting, incoming server-initiated notifications are dispatched to registered listeners; incoming agent-to-client requests are handled synchronously.
- `JsonRpcPeer::notify()` sends a JSON-RPC notification without waiting for a response.
- `JsonRpcPeer::callExtension()` / `notifyExtension()` enforce the ACP rule that custom method names start with `_`.

### ACP convenience methods

High-level wrappers on `$client->acp()` return typed DTOs:

- `initialize(array $params = []): InitializeResult`
- `authenticate(string $methodId, ?float $timeout = null): array`
- `logout(?float $timeout = null): array`
- `sessionNew(string $cwd, array $mcpServers = [], array $additionalDirectories = [], ?float $timeout = null): Session`
- `sessionLoad(string $sessionId, string $cwd, array $mcpServers = [], array $additionalDirectories = [], ?float $timeout = null): mixed`
- `sessionResume(string $sessionId, string $cwd, array $mcpServers = [], array $additionalDirectories = [], ?float $timeout = null): Session`
- `sessionClose(string $sessionId, ?float $timeout = null): Session`
- `sessionList(?string $cwd = null, ?string $cursor = null, ?float $timeout = null): SessionListResult`
- `sessionDelete(string $sessionId, ?float $timeout = null): array`
- `sessionPrompt(string $sessionId, string|array $prompt, ?float $timeout = null, array $meta = []): PromptResult`
- `sessionSlashCommand(string $sessionId, string $command, ?string $input = null, ?float $timeout = null, array $meta = []): PromptResult`
- `sessionCancel(string $sessionId): void`
- `setConfigOption(string $sessionId, string $configId, string $value, ?float $timeout = null): SessionConfigOptionsResult`
- `setMode(string $sessionId, string $modeId, ?float $timeout = null): SessionConfigOptionsResult`

The lower-level `$client->rpc()->call()` and `$client->rpc()->notify()` methods remain available for agent-specific extensions and ACP methods not yet wrapped, and still return raw arrays/mixed values as the escape hatch.

### Notifications and agent requests

- Register notification listeners with `$client->notifications()->onNotification()` or `on('session/update', ...)`.
- Use typed mappers such as `SessionUpdateMapper::fromNotification()` to dispatch `session/update` variants to concrete value objects.
- Register handlers for agent-to-client requests with `$client->requests()->onRequest()`, a fallback with `onAnyRequest()`, a typed permission handler with `onRequestPermission()`, or typed fs/terminal handlers (`onReadTextFile`, `onWriteTextFile`, `onTerminalCreate`, `onTerminalOutput`, `onTerminalWaitForExit`, `onTerminalKill`, `onTerminalRelease`).
- `AgentRequestDispatcher` keeps a single handler per method (a later `onRequest()` for the same method overwrites the previous one). Method-specific handlers take precedence over `onAnyRequest()`, and typed fs/terminal handlers take precedence over a generic `onRequest()` handler for the same method. `clientCapabilities()` advertises `fs` and `terminal` from any registered handler for the matching method, whether registered via the typed helpers or `onRequest()`.

### Protocol strictness

By default `Client` runs in strict protocol mode (`strictProtocol: true`). It validates:

- `initialize()` must be called before session lifecycle methods.
- Advertised capabilities must exist for `session/list`, `session/load`, `session/resume`, `session/close`, `session/delete`, and `additionalDirectories`.
- `cwd`, `additionalDirectories`, and stdio MCP `command` values must be absolute paths.
- MCP server configurations must match the expected stdio/http/sse shapes.
- HTTP/SSE MCP servers require the matching `mcpCapabilities.http`/`mcpCapabilities.sse` capability.
- Prompt content blocks must match advertised `promptCapabilities`; image, audio, and embedded resource blocks are rejected unless advertised.

Disable strict mode with `new Client($transport, strictProtocol: false)` for a permissive thin JSON-RPC wrapper.

## Security Considerations

- Do not commit local credentials, agent tokens, or machine-specific paths.
- In strict protocol mode, stdio commands and directories must be absolute paths.
- Treat all agent responses as untrusted input; DTO factories perform strict validation and throw `AcpException` on malformed data.
- The stdio transport captures stderr to enrich error messages but keeps the buffer capped at 4000 characters.

## Commit and Pull Request Guidelines

- Use concise imperative commit subjects, e.g. `Enforce session/delete capability check in strict protocol mode`.
- Pull requests should include a short summary and the results of:
  - `vendor/bin/phpunit`
  - `vendor/bin/mago analyze`
  - `vendor/bin/mago lint`
- Include protocol compatibility notes when ACP behavior changes.
