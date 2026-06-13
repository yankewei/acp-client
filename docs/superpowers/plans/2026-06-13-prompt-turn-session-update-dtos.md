# Prompt Turn Session Update DTOs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Type all known ACP v1 `session/update` notification variants as immutable value objects under `src/Event/Update/`, with a central `SessionUpdateMapper` dispatcher.

**Architecture:** Define a `SessionUpdate` interface requiring `getSessionId()` and `getUpdateType()`. Add one final value object per `sessionUpdate` discriminator (`agent_message_chunk`, `tool_call`, `tool_call_update`, `usage_update`, `plan`) plus retro-fit the existing `SessionInfoUpdate`. Provide a `SessionUpdateMapper::fromNotification()` that returns the correct subtype or `null` for unknown variants. Keep validation strict but return `null` on unknown types for forward compatibility.

**Tech Stack:** PHP 8.1+, PHPUnit 11, PHPStan level 9.

---

## File Map

| File | Responsibility |
|---|---|
| `src/Event/Update/SessionUpdate.php` | Interface for all typed session updates |
| `src/Event/Update/AgentMessageChunkUpdate.php` | `agent_message_chunk` value object |
| `src/Event/Update/ToolCallUpdate.php` | `tool_call` value object |
| `src/Event/Update/ToolCallStatusUpdate.php` | `tool_call_update` value object |
| `src/Event/Update/UsageUpdate.php` | `usage_update` value object |
| `src/Event/Update/PlanUpdate.php` | `plan` value object + nested `PlanEntry` |
| `src/Event/Update/SessionUpdateMapper.php` | Dispatcher from `Notification` to concrete subtype |
| `src/Event/SessionInfoUpdate.php` | Modify to implement `SessionUpdate` |
| `tests/Event/Update/AgentMessageChunkUpdateTest.php` | Unit tests for `AgentMessageChunkUpdate` |
| `tests/Event/Update/ToolCallUpdateTest.php` | Unit tests for `ToolCallUpdate` |
| `tests/Event/Update/ToolCallStatusUpdateTest.php` | Unit tests for `ToolCallStatusUpdate` |
| `tests/Event/Update/UsageUpdateTest.php` | Unit tests for `UsageUpdate` |
| `tests/Event/Update/PlanUpdateTest.php` | Unit tests for `PlanUpdate` and `PlanEntry` |
| `tests/Event/Update/SessionUpdateMapperTest.php` | Tests for the dispatcher and unknown variants |
| `tests/Event/SessionInfoUpdateTest.php` | Add tests that it implements `SessionUpdate` |
| `README.md` | Add usage example |

---

## Shared Helper

Add three small helpers to `src/Client.php` or `src/Util/Assert.php`? Prefer `src/Util/Assert.php` because these are general validation utilities already used by DTOs.

### Task 0: Add optional enum validation helpers to `src/Util/Assert.php`

**Files:**
- Modify: `src/Util/Assert.php`
- Test: `tests/Util/AssertTest.php` (if it exists; otherwise skip and verify through DTO tests)

- [ ] **Step 1: Add `optionalStringInEnum()`**

```php
/**
 * @param string[] $allowed
 *
 * @throws AcpException
 */
public static function optionalStringInEnum(
    ?string $value,
    array $allowed,
    string $message,
): ?string {
    if ($value === null) {
        return null;
    }

    if (!in_array($value, $allowed, true)) {
        throw new AcpException($message);
    }

    return $value;
}
```

- [ ] **Step 2: Add `optionalList()`**

```php
/**
 * @throws AcpException
 */
public static function optionalList(mixed $value, string $message): array
{
    if ($value === null) {
        return [];
    }

    if (!is_array($value) || !array_is_list($value)) {
        throw new AcpException($message);
    }

    return $value;
}
```

- [ ] **Step 3: Verify with existing tests**

Run: `vendor/bin/phpunit`
Expected: PASS (no behavior changed yet)

- [ ] **Step 4: Commit**

```bash
git add src/Util/Assert.php
git commit -m "Add optional enum and list validation helpers"
```

---

## Task 1: Create `SessionUpdate` interface

**Files:**
- Create: `src/Event/Update/SessionUpdate.php`
- Test: `tests/Event/Update/SessionUpdateTest.php` (optional smoke test)

- [ ] **Step 1: Write interface**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

interface SessionUpdate
{
    public function getSessionId(): string;

    /**
     * Returns the value of params.update.sessionUpdate.
     */
    public function getUpdateType(): string;
}
```

- [ ] **Step 2: Verify PHPStan**

Run: `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Event/Update/SessionUpdate.php
git commit -m "Add SessionUpdate interface"
```

---

## Task 2: Retrofit `SessionInfoUpdate` to implement `SessionUpdate`

**Files:**
- Modify: `src/Event/SessionInfoUpdate.php`
- Test: `tests/Event/SessionInfoUpdateTest.php`

- [ ] **Step 1: Implement interface and add `getUpdateType()`**

Change class declaration to:

```php
use Yankewei\AcpClient\Event\Update\SessionUpdate;

final class SessionInfoUpdate implements SessionUpdate
```

Add method:

```php
public function getUpdateType(): string
{
    return 'session_info_update';
}
```

- [ ] **Step 2: Add/update test**

```php
public function testImplementsSessionUpdate(): void
{
    $update = SessionInfoUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'session_info_update']);

    self::assertInstanceOf(SessionUpdate::class, $update);
    self::assertSame('sess_1', $update->getSessionId());
    self::assertSame('session_info_update', $update->getUpdateType());
}
```

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/SessionInfoUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/SessionInfoUpdate.php tests/Event/SessionInfoUpdateTest.php
git commit -m "Make SessionInfoUpdate implement SessionUpdate"
```

---

## Task 3: Create `AgentMessageChunkUpdate`

**Files:**
- Create: `src/Event/Update/AgentMessageChunkUpdate.php`
- Test: `tests/Event/Update/AgentMessageChunkUpdateTest.php`

- [ ] **Step 1: Write the DTO**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class AgentMessageChunkUpdate implements SessionUpdate
{
    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly ?string $messageId,
        private readonly array $content,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'agent_message_chunk') {
            throw new AcpException('Invalid agent_message_chunk update: sessionUpdate must be agent_message_chunk');
        }

        $content = $update['content'] ?? null;
        if (!is_array($content) || array_is_list($content)) {
            throw new AcpException('Invalid agent_message_chunk update: content must be an object');
        }

        return new self(
            $sessionId,
            Assert::optionalString(
                $update['messageId'] ?? null,
                'Invalid agent_message_chunk update: messageId must be a string or null',
            ),
            $content,
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'agent_message_chunk';
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }
}
```

- [ ] **Step 2: Write tests**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class AgentMessageChunkUpdateTest extends TestCase
{
    public function testParsesTextChunk(): void
    {
        $update = AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'messageId' => 'msg_1',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        self::assertInstanceOf(SessionUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
        self::assertSame('agent_message_chunk', $update->getUpdateType());
        self::assertSame('msg_1', $update->getMessageId());
        self::assertSame(['type' => 'text', 'text' => 'Hello'], $update->getContent());
    }

    public function testMessageIdIsOptional(): void
    {
        $update = AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);

        self::assertNull($update->getMessageId());
    }

    public function testRejectsWrongDiscriminator(): void
    {
        $this->expectException(AcpException::class);

        AgentMessageChunkUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'tool_call']);
    }

    public function testRejectsMissingContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: content must be an object');

        AgentMessageChunkUpdate::fromUpdate('sess_1', ['sessionUpdate' => 'agent_message_chunk']);
    }

    public function testRejectsListContent(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: content must be an object');

        AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'content' => [['type' => 'text', 'text' => 'Hello']],
        ]);
    }

    public function testRejectsInvalidMessageIdType(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid agent_message_chunk update: messageId must be a string or null');

        AgentMessageChunkUpdate::fromUpdate('sess_1', [
            'sessionUpdate' => 'agent_message_chunk',
            'messageId' => 123,
            'content' => ['type' => 'text', 'text' => 'Hello'],
        ]);
    }
}
```

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/AgentMessageChunkUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/AgentMessageChunkUpdate.php tests/Event/Update/AgentMessageChunkUpdateTest.php
git commit -m "Add AgentMessageChunkUpdate DTO"
```

---

## Task 4: Create `ToolCallUpdate`

**Files:**
- Create: `src/Event/Update/ToolCallUpdate.php`
- Test: `tests/Event/Update/ToolCallUpdateTest.php`

- [ ] **Step 1: Write the DTO**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ToolCallUpdate implements SessionUpdate
{
    /** @var string[] */
    private const KINDS = ['read', 'edit', 'delete', 'move', 'search', 'execute', 'think', 'fetch', 'other'];

    /** @var string[] */
    private const STATUSES = ['pending', 'in_progress', 'completed', 'failed'];

    /**
     * @param array<int, mixed> $content
     * @param array<int, mixed> $locations
     * @param array<string, mixed>|null $rawInput
     * @param array<string, mixed>|null $rawOutput
     */
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
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'tool_call') {
            throw new AcpException('Invalid tool_call update: sessionUpdate must be tool_call');
        }

        $toolCallId = Assert::requiredString(
            $update,
            'toolCallId',
            'Invalid tool_call update: toolCallId must be a string',
        );

        $title = Assert::requiredString(
            $update,
            'title',
            'Invalid tool_call update: title must be a string',
        );

        $kind = Assert::optionalStringInEnum(
            $update['kind'] ?? null,
            self::KINDS,
            'Invalid tool_call update: kind must be one of read, edit, delete, move, search, execute, think, fetch, other',
        ) ?? 'other';

        $status = Assert::optionalStringInEnum(
            $update['status'] ?? null,
            self::STATUSES,
            'Invalid tool_call update: status must be one of pending, in_progress, completed, failed',
        ) ?? 'pending';

        $content = Assert::optionalList(
            $update['content'] ?? null,
            'Invalid tool_call update: content must be a list',
        );

        $locations = Assert::optionalList(
            $update['locations'] ?? null,
            'Invalid tool_call update: locations must be a list',
        );

        $rawInput = self::optionalObject($update, 'rawInput', 'Invalid tool_call update: rawInput must be an object');
        $rawOutput = self::optionalObject($update, 'rawOutput', 'Invalid tool_call update: rawOutput must be an object');

        return new self($sessionId, $toolCallId, $title, $kind, $status, $content, $locations, $rawInput, $rawOutput);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function optionalObject(array $data, string $key, string $message): ?array
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new AcpException($message);
        }

        return $value;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'tool_call';
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<int, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return array<int, mixed>
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawInput(): ?array
    {
        return $this->rawInput;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawOutput(): ?array
    {
        return $this->rawOutput;
    }
}
```

- [ ] **Step 2: Write tests**

Cover:
- Happy path with all fields
- Defaults for missing `kind`/`status` (`other` / `pending`)
- Missing required `toolCallId` / `title`
- Invalid enum values for `kind` and `status`
- `content` / `locations` must be lists
- `rawInput` / `rawOutput` must be objects

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/ToolCallUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/ToolCallUpdate.php tests/Event/Update/ToolCallUpdateTest.php
git commit -m "Add ToolCallUpdate DTO"
```

---

## Task 5: Create `ToolCallStatusUpdate`

**Files:**
- Create: `src/Event/Update/ToolCallStatusUpdate.php`
- Test: `tests/Event/Update/ToolCallStatusUpdateTest.php`

- [ ] **Step 1: Write the DTO**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class ToolCallStatusUpdate implements SessionUpdate
{
    /** @var string[] */
    private const STATUSES = ['pending', 'in_progress', 'completed', 'failed'];

    /**
     * @param array<int, mixed> $content
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly string $toolCallId,
        private readonly ?string $status,
        private readonly array $content,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'tool_call_update') {
            throw new AcpException('Invalid tool_call_update update: sessionUpdate must be tool_call_update');
        }

        $toolCallId = Assert::requiredString(
            $update,
            'toolCallId',
            'Invalid tool_call_update update: toolCallId must be a string',
        );

        $status = Assert::optionalStringInEnum(
            $update['status'] ?? null,
            self::STATUSES,
            'Invalid tool_call_update update: status must be one of pending, in_progress, completed, failed',
        );

        $content = Assert::optionalList(
            $update['content'] ?? null,
            'Invalid tool_call_update update: content must be a list',
        );

        return new self($sessionId, $toolCallId, $status, $content);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'tool_call_update';
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return array<int, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }
}
```

- [ ] **Step 2: Write tests**

Cover:
- Happy path with status and content
- Only `toolCallId` present
- Invalid status enum
- `content` must be list

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/ToolCallStatusUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/ToolCallStatusUpdate.php tests/Event/Update/ToolCallStatusUpdateTest.php
git commit -m "Add ToolCallStatusUpdate DTO"
```

---

## Task 6: Create `UsageUpdate`

**Files:**
- Create: `src/Event/Update/UsageUpdate.php`
- Test: `tests/Event/Update/UsageUpdateTest.php`

- [ ] **Step 1: Write the DTO**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class UsageUpdate implements SessionUpdate
{
    /**
     * @param array<string, mixed>|null $cost
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly int $used,
        private readonly int $size,
        private readonly ?array $cost,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'usage_update') {
            throw new AcpException('Invalid usage_update update: sessionUpdate must be usage_update');
        }

        $used = Assert::requiredInt(
            $update,
            'used',
            'Invalid usage_update update: used must be an integer',
        );

        $size = Assert::requiredInt(
            $update,
            'size',
            'Invalid usage_update update: size must be an integer',
        );

        $cost = null;
        if (array_key_exists('cost', $update)) {
            $cost = $update['cost'];
            if (!is_array($cost) || ($cost !== [] && array_is_list($cost))) {
                throw new AcpException('Invalid usage_update update: cost must be an object');
            }

            if (!array_key_exists('amount', $cost) || (!is_int($cost['amount']) && !is_float($cost['amount']) && !is_string($cost['amount']))) {
                throw new AcpException('Invalid usage_update update: cost.amount must be a number');
            }

            $currency = $cost['currency'] ?? null;
            if (!is_string($currency)) {
                throw new AcpException('Invalid usage_update update: cost.currency must be a string');
            }
        }

        return new self($sessionId, $used, $size, $cost);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'usage_update';
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCost(): ?array
    {
        return $this->cost;
    }
}
```

Note: `Assert::requiredInt()` may not exist yet. If it does not, add it to `src/Util/Assert.php` in this task:

```php
/**
 * @param array<string, mixed> $data
 *
 * @throws AcpException
 */
public static function requiredInt(array $data, string $key, string $message): int
{
    if (!array_key_exists($key, $data) || !is_int($data[$key])) {
        throw new AcpException($message);
    }

    return $data[$key];
}
```

- [ ] **Step 2: Write tests**

Cover:
- Happy path with cost
- Happy path without cost
- Missing `used` / `size`
- Non-integer `used` / `size`
- `cost` not an object
- `cost.amount` not a number
- `cost.currency` not a string

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/UsageUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/UsageUpdate.php tests/Event/Update/UsageUpdateTest.php
git commit -m "Add UsageUpdate DTO"
```

---

## Task 7: Create `PlanUpdate` and `PlanEntry`

**Files:**
- Create: `src/Event/Update/PlanUpdate.php`
- Test: `tests/Event/Update/PlanUpdateTest.php`

- [ ] **Step 1: Write the DTOs in one file**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class PlanUpdate implements SessionUpdate
{
    /**
     * @param PlanEntry[] $entries
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly array $entries,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @throws AcpException
     */
    public static function fromUpdate(string $sessionId, array $update): self
    {
        if (($update['sessionUpdate'] ?? null) !== 'plan') {
            throw new AcpException('Invalid plan update: sessionUpdate must be plan');
        }

        $entries = $update['entries'] ?? null;
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new AcpException('Invalid plan update: entries must be a list');
        }

        return new self(
            $sessionId,
            array_map(
                static function (mixed $entry, int $index): PlanEntry {
                    if (!is_array($entry) || array_is_list($entry)) {
                        throw new AcpException("Invalid plan update: entries[{$index}] must be an object");
                    }

                    return PlanEntry::fromArray($entry);
                },
                $entries,
                array_keys($entries),
            ),
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdateType(): string
    {
        return 'plan';
    }

    /**
     * @return PlanEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

final class PlanEntry
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $priority,
        private readonly ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    public static function fromArray(array $data): self
    {
        $content = Assert::requiredString(
            $data,
            'content',
            'Invalid plan entry: content must be a string',
        );

        $priority = Assert::optionalString(
            $data['priority'] ?? null,
            'Invalid plan entry: priority must be a string or null',
        );

        $status = Assert::optionalString(
            $data['status'] ?? null,
            'Invalid plan entry: status must be a string or null',
        );

        return new self($content, $priority, $status);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
```

- [ ] **Step 2: Write tests**

Cover:
- Happy path with multiple entries
- Missing `entries`
- `entries` not a list
- Entry not an object
- Entry missing `content`
- Entry invalid `priority`/`status` type

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/PlanUpdateTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/PlanUpdate.php tests/Event/Update/PlanUpdateTest.php
git commit -m "Add PlanUpdate and PlanEntry DTOs"
```

---

## Task 8: Create `SessionUpdateMapper`

**Files:**
- Create: `src/Event/Update/SessionUpdateMapper.php`
- Test: `tests/Event/Update/SessionUpdateMapperTest.php`

- [ ] **Step 1: Write the mapper**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Event\Update;

use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Event\SessionInfoUpdate;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Assert;

final class SessionUpdateMapper
{
    /**
     * @throws AcpException
     */
    public static function fromNotification(Notification $notification): ?SessionUpdate
    {
        if (!$notification->is('session/update')) {
            return null;
        }

        $params = $notification->getParams();
        $sessionId = Assert::requiredString(
            $params,
            'sessionId',
            'Invalid session/update notification: sessionId must be a string',
        );

        $update = $params['update'] ?? null;
        if (!is_array($update) || array_is_list($update)) {
            return null;
        }

        $type = $update['sessionUpdate'] ?? null;
        if (!is_string($type)) {
            return null;
        }

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

- [ ] **Step 2: Write tests**

```php
<?php

declare(strict_types=1);

namespace Yankewei\AcpClient\Tests\Event\Update;

use PHPUnit\Framework\TestCase;
use Yankewei\AcpClient\Event\Notification;
use Yankewei\AcpClient\Event\SessionInfoUpdate;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\PlanUpdate;
use Yankewei\AcpClient\Event\Update\SessionUpdateMapper;
use Yankewei\AcpClient\Event\Update\ToolCallStatusUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallUpdate;
use Yankewei\AcpClient\Event\Update\UsageUpdate;
use Yankewei\AcpClient\Exception\AcpException;

final class SessionUpdateMapperTest extends TestCase
{
    public function testReturnsNullForNonSessionUpdateNotification(): void
    {
        $notification = new Notification('other', []);

        self::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testReturnsNullForUnknownUpdateType(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => ['sessionUpdate' => 'future_update'],
        ]);

        self::assertNull(SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesAgentMessageChunk(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'agent_message_chunk',
                'content' => ['type' => 'text', 'text' => 'Hi'],
            ],
        ]);

        $update = SessionUpdateMapper::fromNotification($notification);

        self::assertInstanceOf(AgentMessageChunkUpdate::class, $update);
        self::assertSame('sess_1', $update->getSessionId());
    }

    public function testDispatchesToolCall(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'tool_call',
                'toolCallId' => 'call_1',
                'title' => 'Run command',
            ],
        ]);

        self::assertInstanceOf(ToolCallUpdate::class, SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesToolCallUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'tool_call_update',
                'toolCallId' => 'call_1',
            ],
        ]);

        self::assertInstanceOf(ToolCallStatusUpdate::class, SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesUsageUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'usage_update',
                'used' => 100,
                'size' => 200,
            ],
        ]);

        self::assertInstanceOf(UsageUpdate::class, SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesPlanUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'plan',
                'entries' => [['content' => 'Step 1']],
            ],
        ]);

        self::assertInstanceOf(PlanUpdate::class, SessionUpdateMapper::fromNotification($notification));
    }

    public function testDispatchesSessionInfoUpdate(): void
    {
        $notification = new Notification('session/update', [
            'sessionId' => 'sess_1',
            'update' => [
                'sessionUpdate' => 'session_info_update',
                'title' => 'New title',
            ],
        ]);

        self::assertInstanceOf(SessionInfoUpdate::class, SessionUpdateMapper::fromNotification($notification));
    }

    public function testRejectsMissingSessionId(): void
    {
        $this->expectException(AcpException::class);
        $this->expectExceptionMessage('Invalid session/update notification: sessionId must be a string');

        SessionUpdateMapper::fromNotification(new Notification('session/update', [
            'update' => ['sessionUpdate' => 'agent_message_chunk', 'content' => ['type' => 'text', 'text' => 'Hi']],
        ]));
    }
}
```

- [ ] **Step 3: Run tests**

Run: `vendor/bin/phpunit tests/Event/Update/SessionUpdateMapperTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Event/Update/SessionUpdateMapper.php tests/Event/Update/SessionUpdateMapperTest.php
git commit -m "Add SessionUpdateMapper dispatcher"
```

---

## Task 9: Update README with usage example

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add example after the Notifications section**

Insert after the existing `session/update` raw-notification example:

```markdown
You can also use the typed `SessionUpdateMapper` to dispatch to concrete
update objects:

```php
use Yankewei\AcpClient\Event\Update\SessionUpdateMapper;
use Yankewei\AcpClient\Event\Update\AgentMessageChunkUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallUpdate;
use Yankewei\AcpClient\Event\Update\ToolCallStatusUpdate;

$client->on('session/update', function (Notification $notification): void {
    $update = SessionUpdateMapper::fromNotification($notification);

    match (true) {
        $update instanceof AgentMessageChunkUpdate => handleMessageChunk($update),
        $update instanceof ToolCallUpdate => handleToolCall($update),
        $update instanceof ToolCallStatusUpdate => handleToolCallUpdate($update),
        default => {},
    };
});
```

Unknown `sessionUpdate` variants return `null` so newer agent features do not
break existing clients.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "Document typed SessionUpdateMapper usage"
```

---

## Task 10: Full verification

- [ ] **Step 1: Run full PHPUnit suite**

Run: `vendor/bin/phpunit`
Expected: PASS (all tests)

- [ ] **Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`
Expected: No errors

- [ ] **Step 3: Final commit if any fixes were needed**

If any fixes were made during verification, commit them.

---

## Spec Coverage Check

| Spec Requirement | Implementing Task |
|---|---|
| `SessionUpdate` interface | Task 1 |
| `SessionInfoUpdate` implements interface | Task 2 |
| `agent_message_chunk` DTO | Task 3 |
| `tool_call` DTO | Task 4 |
| `tool_call_update` DTO | Task 5 |
| `usage_update` DTO | Task 6 |
| `plan` DTO + `PlanEntry` | Task 7 |
| `SessionUpdateMapper` dispatcher | Task 8 |
| Return `null` for unknown variants | Task 8 |
| README usage example | Task 9 |
| Unit tests for each DTO and mapper | Tasks 3-8 |

## Placeholder Scan

- No TBD/TODO placeholders.
- Each task includes exact file paths.
- Code blocks contain complete class/test skeletons.
- Commands include expected outputs.

## Type Consistency Check

- `SessionUpdate::getUpdateType()` returns `string` consistently.
- `fromUpdate(string $sessionId, array $update): self` signature is consistent across all update DTOs.
- `Assert::optionalStringInEnum()` and `Assert::optionalList()` are added once in Task 0 and reused.
