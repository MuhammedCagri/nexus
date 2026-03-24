<?php

declare(strict_types=1);

namespace Nexus\Message;

use Nexus\Enum\Role;

/**
 * Immutable value object representing a single conversation message.
 *
 * @package Nexus\Message
 */
final class Message
{
    /**
     * @param Role         $role       Conversation role for this message.
     * @param string       $content    Text body.
     * @param ToolCall[]   $toolCalls  Tool invocations attached to an assistant message.
     * @param string|null  $toolCallId Correlates a tool-result message to its call.
     * @param string|null  $name       Optional tool name for tool-result messages.
     */
    public function __construct(
        public Role $role,
        public string $content = '',
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {
    }

    /**
     * @param string $content System prompt text.
     *
     * @return self
     */
    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    /**
     * @param string $content User input text.
     *
     * @return self
     */
    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }

    /**
     * @param string     $content   Assistant reply text.
     * @param ToolCall[] $toolCalls Tool invocations requested by the assistant.
     *
     * @return self
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        return new self(Role::Assistant, $content, $toolCalls);
    }

    /**
     * @param string      $content    Tool execution result.
     * @param string      $toolCallId Identifier of the originating tool call.
     * @param string|null $name       Tool function name.
     *
     * @return self
     */
    public static function tool(string $content, string $toolCallId, ?string $name = null): self
    {
        return new self(Role::Tool, $content, toolCallId: $toolCallId, name: $name);
    }

    /**
     * @return bool True when the message carries one or more tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Serialize to an API-compatible associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->toolCalls !== []) {
            $data['tool_calls'] = array_map(
                fn (ToolCall $tc) => $tc->toArray(),
                $this->toolCalls,
            );
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }
}
