# Design: Typed Session Update DTOs for Prompt Turn Lifecycle

## Goal

Type the JSON-RPC `session/update` notifications that the Agent sends during a prompt turn, so consumers can switch on concrete value objects instead of parsing raw `Notification` params.

## Background

ACP v1 defines `session/update` as a server-initiated notification with a discriminator field `update.sessionUpdate`. The library already handles one variant (`session_info_update`) via `SessionInfoUpdateMapper` and `SessionInfoUpdate`. The prompt-turn and tool-calls protocols define several more variants:

- `agent_message_chunk`
- `tool_call`
- `tool_call_update`
- `usage_update`
- `plan`

This design extends the existing pattern to cover all known variants.

## Scope

**In scope:**

- Define a common `SessionUpdate` interface.
- Add typed value objects for each `session/update` variant listed above.
- Make the existing `SessionInfoUpdate` implement `SessionUpdate`.
- Add a `SessionUpdateMapper::fromNotification()` dispatcher.
- Add unit tests for each DTO and the mapper.
- Update `README.md` with usage example.

**Out of scope:**

- `session/request_permission` handling.
- Full cancellation state machine for pending tool calls / permission requests.
- Nested content block validation inside tool-call content (text/image/audio/resource are accepted as raw arrays; the library already validates content blocks for prompts, and tool-call content can reuse the same structure later if needed).

## Design

### Directory layout

```
src/Event/Update/
├── SessionUpdate.php              # interface
├── SessionUpdateMapper.php        # dispatcher
├── AgentMessageChunkUpdate.php
├── ToolCallUpdate.php
├── ToolCallStatusUpdate.php
├── UsageUpdate.php
├── PlanUpdate.php
└── ... existing SessionInfoUpdate stays in src/Event/SessionInfoUpdate.php
```

Tests mirror source layout:

```
tests/Event/Update/
├── SessionUpdateMapperTest.php
├── AgentMessageChunkUpdateTest.php
├── ToolCallUpdateTest.php
├── ToolCallStatusUpdateTest.php
├── UsageUpdateTest.php
└── PlanUpdateTest.php
```

### Interface

```php
namespace Yankewei\AcpClient\Event\Update;

interface SessionUpdate
{
    public function getSessionId(): string;

    /**
     * Returns the value of params.update.sessionUpdate, e.g. "agent_message_chunk".
     */
    public function getUpdateType(): string;
}
```

### Value objects

Each value object is `final`, immutable, constructed via a static `fromUpdate(string $sessionId, array $update): self` factory, and exposes strongly typed getters. Validation errors throw `AcpException` with specific messages.

#### AgentMessageChunkUpdate

```php
final class AgentMessageChunkUpdate implements SessionUpdate
{
    public function __construct(
        private readonly string $sessionId,
        private readonly ?string $messageId,
        private readonly array $content, // single ContentBlock as associative array
    ) {}

    public static function fromUpdate(string $sessionId, array $update): self;
    public function getSessionId(): string;
    public function getUpdateType(): string;
    public function getMessageId(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array;
}
```

Protocol shape:

```json
{
  "sessionUpdate": "agent_message_chunk",
  "messageId": "msg_...",
  "content": { "type": "text", "text": "..." }
}
```

#### ToolCallUpdate

```php
final class ToolCallUpdate implements SessionUpdate
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $toolCallId,
        private readonly string $title,
        private readonly string $kind,
        private readonly string $status,
        private readonly array $content,
        private readonly array $locations,
        private readonly ?array $rawInput,
        private readonly ?array $rawOutput,
    ) {}

    public static function fromUpdate(string $sessionId, array $update): self;
    // getters
}
```

Protocol shape:

```json
{
  "sessionUpdate": "tool_call",
  "toolCallId": "call_001",
  "title": "Reading configuration file",
  "kind": "read",
  "status": "pending",
  "content": [...],
  "locations": [...],
  "rawInput": {...},
  "rawOutput": {...}
}
```

Validation:

- `toolCallId` and `title` are required strings.
- `kind`, if present, must be one of: `read`, `edit`, `delete`, `move`, `search`, `execute`, `think`, `fetch`, `other`.
- `status`, if present, must be one of: `pending`, `in_progress`, `completed`, `failed`.
- `content`, `locations`, `rawInput`, `rawOutput` are objects/lists validated only for shape (list vs object), not deeply.

#### ToolCallStatusUpdate

```php
final class ToolCallStatusUpdate implements SessionUpdate
{
    public function __construct(
        private readonly string $sessionId,
        private readonly string $toolCallId,
        private readonly ?string $status,
        private readonly array $content,
    ) {}

    public static function fromUpdate(string $sessionId, array $update): self;
    // getters
}
```

Protocol shape:

```json
{
  "sessionUpdate": "tool_call_update",
  "toolCallId": "call_001",
  "status": "in_progress",
  "content": [...]
}
```

Validation:

- `toolCallId` is required string.
- `status`, if present, must be one of the known statuses.
- `content`, if present, must be a list.

#### UsageUpdate

```php
final class UsageUpdate implements SessionUpdate
{
    public function __construct(
        private readonly string $sessionId,
        private readonly int $used,
        private readonly int $size,
        private readonly ?array $cost,
    ) {}

    public static function fromUpdate(string $sessionId, array $update): self;
    // getters
}
```

Protocol shape:

```json
{
  "sessionUpdate": "usage_update",
  "used": 53000,
  "size": 200000,
  "cost": { "amount": 0.045, "currency": "USD" }
}
```

Validation:

- `used` and `size` are required integers.
- `cost`, if present, must be an object with `amount` (string|float|int) and `currency` (string).

#### PlanUpdate

```php
final class PlanUpdate implements SessionUpdate
{
    /**
     * @param PlanEntry[] $entries
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $entries,
    ) {}

    public static function fromUpdate(string $sessionId, array $update): self;
    // getters
}

final class PlanEntry
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $priority,
        private readonly ?string $status,
    ) {}

    public static function fromArray(array $data): self;
    public function getContent(): string;
    public function getPriority(): ?string;
    public function getStatus(): ?string;
}
```

Protocol shape:

```json
{
  "sessionUpdate": "plan",
  "entries": [
    { "content": "Check for syntax errors", "priority": "high", "status": "pending" }
  ]
}
```

Validation:

- `entries` is a required list of objects.
- Each entry has `content` (required string), `priority` (optional string), `status` (optional string).

### Mapper

```php
namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Event\Notification;

final class SessionUpdateMapper
{
    public static function fromNotification(Notification $notification): ?SessionUpdate
    {
        if (!$notification->is('session/update')) {
            return null;
        }

        $params = $notification->getParams();
        $sessionId = // require string
        $update = // require object
        $type = $update['sessionUpdate'] ?? null;

        return match ($type) {
            'session_info_update' => SessionInfoUpdate::fromUpdate($sessionId, $update),
            'agent_message_chunk' => AgentMessageChunkUpdate::fromUpdate($sessionId, $update),
            'tool_call' => ToolCallUpdate::fromUpdate($sessionId, $update),
            'tool_call_update' => ToolCallStatusUpdate::fromUpdate($sessionId, $update),
            'usage_update' => UsageUpdate::fromUpdate($sessionId, $update),
            'plan' => PlanUpdate::fromUpdate($sessionId, $update),
            default => null,
        };
    }
}
```

Notes:

- Returning `null` for unknown variants preserves forward compatibility when Agents introduce new `sessionUpdate` types.
- Existing `SessionInfoUpdateMapper` can remain for backward compatibility, but the README will steer users toward `SessionUpdateMapper`.

## Usage

```php
use Yankewei\AcpClient\Event\Update\SessionUpdateMapper;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallUpdate;

$client->on('session/update', function (Notification $notification): void {
    $update = SessionUpdateMapper::fromNotification($notification);

    match (true) {
        $update instanceof AgentMessageChunkUpdate => handleMessageChunk($update),
        $update instanceof ToolCallUpdate => handleToolCall($update),
        default => {},
    };
});
```

## Testing

For each DTO:

- Happy-path parsing from a protocol-shaped array.
- Rejection of missing required fields.
- Rejection of wrong types.
- Rejection of invalid enum values (`kind`, `status`, `priority`).

For the mapper:

- Returns the correct subtype for each known `sessionUpdate` value.
- Returns `null` for non-`session/update` notifications.
- Returns `null` for unknown `sessionUpdate` values.

## Risks and Trade-offs

- **Forward compatibility:** Unknown `sessionUpdate` values return `null`. Consumers must handle `null`. This is safer than throwing, because new variants should not break existing clients.
- **Deep validation scope:** Tool-call `content` arrays are validated as lists but not deeply parsed into content blocks. Deep parsing can be added later without breaking the public API.
- **Backward compatibility:** `SessionInfoUpdateMapper` is kept unchanged. `SessionInfoUpdate` will implement `SessionUpdate` but keeps its existing public methods.

## Future Work

- `session/request_permission` helper.
- Cancellation state machine for pending tool calls and permission requests.
- Deep content-block parsing inside tool-call content if the library later wants to validate it.
