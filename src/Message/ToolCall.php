<?php

declare(strict_types=1);

namespace Nexus\Message;

/**
 * Value object for a single tool/function invocation within a message.
 *
 * @package Nexus\Message
 */
final class ToolCall
{
    /**
     * @param string               $id        Provider-assigned call identifier.
     * @param string               $name      Function name to invoke.
     * @param array<string, mixed> $arguments Decoded function arguments.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
    }

    /**
     * Hydrate from a raw API response array (OpenAI / Anthropic formats).
     *
     * @param array<string, mixed> $data Raw tool_call element from the provider.
     *
     * @return self
     *
     * @throws \JsonException When JSON-encoded arguments cannot be decoded.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['function']['name'] ?? $data['name'] ?? '',
            arguments: is_string($data['function']['arguments'] ?? null)
                ? json_decode($data['function']['arguments'], true, 512, JSON_THROW_ON_ERROR)
                : ($data['function']['arguments'] ?? $data['arguments'] ?? $data['input'] ?? []),
        );
    }

    /**
     * Serialize to an OpenAI-compatible tool_call array.
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException When arguments cannot be JSON-encoded.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => json_encode($this->arguments, JSON_THROW_ON_ERROR),
            ],
        ];
    }
}
