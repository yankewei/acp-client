<?php

declare(strict_types=1);

namespace Yankewei\AcpClient;

use Yankewei\AcpClient\Dto\ContentBlock\ContentBlockType;
use Yankewei\AcpClient\Dto\InitializeResult;
use Yankewei\AcpClient\Exception\AcpException;
use Yankewei\AcpClient\Util\Path;

final class ProtocolValidator
{
    public function __construct(
        private readonly bool $strictProtocol = true,
    ) {}

    public function isStrict(): bool
    {
        return $this->strictProtocol;
    }

    /**
     * @throws AcpException
     */
    public function requireInitialized(string $method, ?InitializeResult $initializeResult): InitializeResult
    {
        if ($initializeResult === null) {
            throw new AcpException("Cannot call {$method} before initialize() in strict protocol mode");
        }

        return $initializeResult;
    }

    /**
     * @param array<int, mixed> $mcpServers
     * @param string[] $additionalDirectories
     *
     * @throws AcpException
     */
    public function validateSessionSetup(
        string $method,
        string $cwd,
        array $mcpServers,
        array $additionalDirectories,
        ?InitializeResult $initializeResult,
    ): void {
        if (!$this->strictProtocol) {
            return;
        }

        $initializeResult = $this->requireInitialized($method, $initializeResult);

        if (!Path::isAbsolutePath($cwd)) {
            throw new AcpException("Invalid {$method} params: cwd must be an absolute path");
        }

        if ($additionalDirectories !== [] && !$initializeResult->supportsAdditionalDirectories()) {
            throw new AcpException(
                "Cannot call {$method} with additionalDirectories: agent did not advertise sessionCapabilities.additionalDirectories",
            );
        }

        foreach ($additionalDirectories as $directory) {
            if (!Path::isAbsolutePath($directory)) {
                throw new AcpException(
                    "Invalid {$method} params: additionalDirectories entries must be absolute paths",
                );
            }
        }

        $this->validateMcpServers($method, $mcpServers, $initializeResult);
    }

    /**
     * @throws AcpException
     */
    public function validateSessionList(?string $cwd, ?InitializeResult $initializeResult): void
    {
        if (!$this->strictProtocol) {
            return;
        }

        if (!$this->requireInitialized('session/list', $initializeResult)->supportsSessionList()) {
            throw new AcpException('Cannot call session/list: agent did not advertise sessionCapabilities.list');
        }

        if ($cwd !== null && !Path::isAbsolutePath($cwd)) {
            throw new AcpException('Invalid session/list params: cwd must be an absolute path');
        }
    }

    /**
     * @param array<int, mixed> $prompt
     *
     * @throws AcpException
     */
    public function validatePrompt(string $method, array $prompt, ?InitializeResult $initializeResult): void
    {
        if (!$this->strictProtocol) {
            return;
        }

        $initializeResult = $this->requireInitialized($method, $initializeResult);

        foreach ($prompt as $index => $block) {
            $block = $this->requireObjectValue($method, "prompt[{$index}]", $block);
            $type = $block['type'] ?? null;
            if (!is_string($type)) {
                throw new AcpException("Invalid {$method} params: prompt[{$index}].type must be a string");
            }

            $contentBlockType = ContentBlockType::tryFrom($type);
            if ($contentBlockType === null) {
                throw new AcpException(
                    "Invalid {$method} params: prompt[{$index}].type is not a supported content block type",
                );
            }

            $this->validateOptionalObjectField($method, "prompt[{$index}].annotations", $block, 'annotations');

            if (!$contentBlockType->isSupportedBy($initializeResult)) {
                $capability = $contentBlockType->capability();
                throw new AcpException(
                    "Cannot call {$method} with {$contentBlockType->value} content: agent did not advertise {$capability}",
                );
            }

            match ($contentBlockType) {
                ContentBlockType::Text => $this->validateTextContentBlock($method, $block, $index),
                ContentBlockType::ResourceLink => $this->validateResourceLinkContentBlock($method, $block, $index),
                ContentBlockType::Image, ContentBlockType::Audio => $this->validateMediaContentBlock(
                    $method,
                    $block,
                    $index,
                    $contentBlockType->value,
                ),
                ContentBlockType::Resource => $this->validateEmbeddedContextContentBlock($method, $initializeResult, $block, $index),
            };
        }
    }

    /**
     * @param array<int, mixed> $mcpServers
     *
     * @throws AcpException
     */
    private function validateMcpServers(string $method, array $mcpServers, InitializeResult $initializeResult): void
    {
        if (!array_is_list($mcpServers)) {
            throw new AcpException("Invalid {$method} params: mcpServers must be a list");
        }

        foreach ($mcpServers as $index => $server) {
            $server = $this->requireObjectValue($method, "mcpServers[{$index}]", $server);

            $type = $server['type'] ?? 'stdio';
            if (!is_string($type)) {
                throw new AcpException("Invalid {$method} params: mcpServers[{$index}].type must be a string");
            }

            match ($type) {
                'stdio' => $this->validateStdioMcpServer($method, $index, $server),
                'http' => $this->validateHttpMcpServer($method, $index, $server, $initializeResult),
                'sse' => $this->validateSseMcpServer($method, $index, $server, $initializeResult),
                default => throw new AcpException(
                    "Invalid {$method} params: mcpServers[{$index}].type must be stdio, http, or sse",
                ),
            };
        }
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateStdioMcpServer(string $method, int $index, array $server): void
    {
        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $command = $this->requireStringField($method, "mcpServers[{$index}].command", $server, 'command');
        if (!Path::isAbsolutePath($command)) {
            throw new AcpException("Invalid {$method} params: mcpServers[{$index}].command must be an absolute path");
        }

        $this->requireStringListField($method, "mcpServers[{$index}].args", $server, 'args');

        $this->validateNameValueList($method, "mcpServers[{$index}].env", $server['env'] ?? null);
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateHttpMcpServer(
        string $method,
        int $index,
        array $server,
        InitializeResult $initializeResult,
    ): void {
        if (!$initializeResult->supportsMcpHttp()) {
            throw new AcpException(
                "Cannot call {$method} with HTTP MCP server: agent did not advertise mcpCapabilities.http",
            );
        }

        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $this->requireStringField($method, "mcpServers[{$index}].url", $server, 'url');
        $this->validateNameValueList($method, "mcpServers[{$index}].headers", $server['headers'] ?? null);
    }

    /**
     * @param array<string, mixed> $server
     *
     * @throws AcpException
     */
    private function validateSseMcpServer(
        string $method,
        int $index,
        array $server,
        InitializeResult $initializeResult,
    ): void {
        if (!$initializeResult->supportsMcpSse()) {
            throw new AcpException(
                "Cannot call {$method} with SSE MCP server: agent did not advertise mcpCapabilities.sse",
            );
        }

        $this->requireStringField($method, "mcpServers[{$index}].name", $server, 'name');
        $this->requireStringField($method, "mcpServers[{$index}].url", $server, 'url');
        $this->validateNameValueList($method, "mcpServers[{$index}].headers", $server['headers'] ?? null);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws AcpException
     */
    private function requireStringField(string $method, string $label, array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || $data[$key] === '') {
            throw new AcpException("Invalid {$method} params: {$label} must be a non-empty string");
        }

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws AcpException
     */
    private function requireStringListField(string $method, string $label, array $data, string $key): void
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key]) || !array_is_list($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be a list of strings");
        }

        foreach ($data[$key] as $value) {
            if (!is_string($value)) {
                throw new AcpException("Invalid {$method} params: {$label} must be a list of strings");
            }
        }
    }

    /**
     * @throws AcpException
     */
    private function validateNameValueList(string $method, string $label, mixed $value): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new AcpException("Invalid {$method} params: {$label} must be a list of name/value objects");
        }

        foreach ($value as $index => $entry) {
            $entry = $this->requireObjectValue($method, "{$label}[{$index}]", $entry);

            $this->requireStringField($method, "{$label}[{$index}].name", $entry, 'name');
            $this->requireStringField($method, "{$label}[{$index}].value", $entry, 'value');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AcpException
     */
    private function requireObjectValue(string $method, string $label, mixed $value): array
    {
        if (!is_array($value) || $value !== [] && array_is_list($value)) {
            throw new AcpException("Invalid {$method} params: {$label} must be an object");
        }

        $object = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw new AcpException("Invalid {$method} params: {$label} must be an object");
            }

            $object[$key] = $entry;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateTextContentBlock(string $method, array $block, int $index): void
    {
        if (!array_key_exists('text', $block) || !is_string($block['text'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].text must be a string");
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateResourceLinkContentBlock(string $method, array $block, int $index): void
    {
        if (!array_key_exists('uri', $block) || !is_string($block['uri'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].uri must be a string");
        }

        if (!array_key_exists('name', $block) || !is_string($block['name'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].name must be a string");
        }

        $this->validateOptionalStringField($method, "prompt[{$index}].mimeType", $block, 'mimeType');
        $this->validateOptionalStringField($method, "prompt[{$index}].title", $block, 'title');
        $this->validateOptionalStringField($method, "prompt[{$index}].description", $block, 'description');
        $this->validateOptionalIntField($method, "prompt[{$index}].size", $block, 'size');
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateMediaContentBlock(
        string $method,
        array $block,
        int $index,
        string $type,
    ): void {
        if (!array_key_exists('data', $block) || !is_string($block['data'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].data must be a string");
        }

        if (!array_key_exists('mimeType', $block) || !is_string($block['mimeType'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].mimeType must be a string");
        }

        if ($type === 'image') {
            $this->validateOptionalStringField($method, "prompt[{$index}].uri", $block, 'uri');
        }
    }

    /**
     * @param array<string, mixed> $block
     *
     * @throws AcpException
     */
    private function validateEmbeddedContextContentBlock(
        string $method,
        InitializeResult $initializeResult,
        array $block,
        int $index,
    ): void {
        if (!$initializeResult->supportsPromptEmbeddedContext()) {
            throw new AcpException(
                "Cannot call {$method} with resource content: agent did not advertise promptCapabilities.embeddedContext",
            );
        }

        if (!array_key_exists('resource', $block)) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource must be an object");
        }

        $resource = $this->requireObjectValue($method, "prompt[{$index}].resource", $block['resource']);

        if (!array_key_exists('uri', $resource) || !is_string($resource['uri'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.uri must be a string");
        }

        $hasText = array_key_exists('text', $resource);
        $hasBlob = array_key_exists('blob', $resource);
        if (!$hasText && !$hasBlob) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource must include text or blob");
        }

        if ($hasText && $hasBlob) {
            throw new AcpException(
                "Invalid {$method} params: prompt[{$index}].resource cannot include both text and blob",
            );
        }

        if ($hasText && !is_string($resource['text'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.text must be a string");
        }

        if ($hasBlob && !is_string($resource['blob'])) {
            throw new AcpException("Invalid {$method} params: prompt[{$index}].resource.blob must be a string");
        }

        $this->validateOptionalStringField($method, "prompt[{$index}].resource.mimeType", $resource, 'mimeType');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalStringField(string $method, string $label, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && !is_string($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be a string");
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalIntField(string $method, string $label, array $data, string $key): void
    {
        if (array_key_exists($key, $data) && !is_int($data[$key])) {
            throw new AcpException("Invalid {$method} params: {$label} must be an integer");
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws AcpException
     */
    private function validateOptionalObjectField(string $method, string $label, array $data, string $key): void
    {
        if (!array_key_exists($key, $data)) {
            return;
        }

        $this->requireObjectValue($method, $label, $data[$key]);
    }
}
