# Repository Guidelines

## Project Structure & Module Organization

This is a PHP 8.1 library for ACP (Agent Communication Protocol). Source code lives in `src/` under the `Yankewei\AcpClient\` PSR-4 namespace. Key areas are `src/Transport/` for transport implementations, `src/JsonRpc/` for protocol messages, `src/Dto/` for typed result objects, `src/Event/` for notifications, and `src/Exception/` for domain errors. Tests live in `tests/` under `Yankewei\AcpClient\Tests\`, with fixtures in `tests/Fixtures/`. Example scripts belong in `examples/`.

## Build, Test, and Development Commands

- `composer update`: install or refresh development dependencies.
- `vendor/bin/phpunit`: run the full PHPUnit suite configured by `phpunit.xml`.
- `vendor/bin/mago analyze`: run static analysis (replaces PHPStan).
- `vendor/bin/mago lint`: run code style linting.
- `vendor/bin/mago format`: apply code formatting.
- `php examples/kimi-smoke.php`: run the optional local smoke test against `kimi acp` when Kimi Code is installed.

Run tests, `mago analyze`, and `mago lint` before submitting changes that affect library behavior.

## Coding Style & Naming Conventions

Use `declare(strict_types=1);` in PHP files. Follow PSR-4 file and namespace mapping: `src/Dto/Session.php` defines `Yankewei\AcpClient\Dto\Session`, and tests mirror source names such as `tests/Dto/SessionTest.php`. Prefer `final` classes unless extension is intentionally part of the API. Use typed properties, explicit return types, and PHPDoc array shapes where PHP types cannot express structure. Keep JSON-RPC and ACP validation errors specific and actionable. Code formatting follows PER-CS defaults via `mago format`. Code style is enforced by `mago lint`, and static analysis by `mago analyze`.

## Testing Guidelines

PHPUnit is the test framework. Name test files `*Test.php` and test methods `test...()`. Put shared fake implementations in `tests/` only when they are reused, as with `tests/FakeTransport.php`. Add or update tests for protocol validation, DTO parsing, transport behavior, and error handling. Fixture agents used by stdio tests should stay in `tests/Fixtures/`.

## Commit & Pull Request Guidelines

Recent commits use concise imperative subjects, for example `Enforce session/delete capability check in strict protocol mode` and `Add ACP authentication discovery support`. Keep subjects focused on the behavior changed. Pull requests should include a short summary, test results (`vendor/bin/phpunit`, `mago analyze`, `mago lint`), and any protocol compatibility notes. Link related issues when available. Include screenshots only for documentation or terminal-output changes where visuals help.

## Security & Configuration Tips

Do not commit local credentials, agent tokens, or machine-specific paths. Stdio command validation expects absolute paths in strict protocol mode, so keep examples explicit and portable. Treat external agent responses as untrusted input and preserve strict DTO validation when adding new ACP methods.
