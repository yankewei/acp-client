<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

/**
 * Specification for a typed agent-to-client request handler.
 *
 * Pairs a request DTO parser with a result normalizer and the client
 * capability (group/field) that registering a handler for this method
 * should advertise in initialize().
 *
 * @internal
 */
final class TypedRequestSpec
{
    /**
     * @param \Closure $parse callable(array<string, mixed>): object that builds the typed request DTO
     * @param \Closure $normalize callable(mixed): mixed that coerces the handler return value to a JSON-RPC result
     * @param string $capabilityGroup top-level capability group (e.g. "fs", "terminal")
     * @param string|null $capabilityField nested capability field, or null when the group is a single boolean
     */
    public function __construct(
        public readonly \Closure $parse,
        public readonly \Closure $normalize,
        public readonly string $capabilityGroup,
        public readonly ?string $capabilityField,
    ) {}
}
