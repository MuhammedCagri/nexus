<?php

declare(strict_types=1);

namespace Nexus\Response;

use Nexus\Enum\FinishReason;
use Nexus\Message\ToolCall;

/**
 * Parsed result of a non-streamed LLM completion.
 *
 * @package Nexus\Response
 */
final class Response
{
    /**
     * @param string                                                                   $content      Generated text.
     * @param FinishReason                                                             $finishReason Why generation stopped.
     * @param ToolCall[]                                                               $toolCalls    Tool invocations requested by the model.
     * @param array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}  $usage        Token usage counters.
     * @param string|null                                                              $model        Model identifier echoed by the provider.
     * @param array<string, mixed>                                                     $meta         Arbitrary provider metadata.
     */
    public function __construct(
        public string $content,
        public FinishReason $finishReason,
        public array $toolCalls = [],
        public array $usage = [],
        public ?string $model = null,
        public array $meta = [],
    ) {
    }

    /**
     * @return bool True when the response contains one or more tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Decode content as JSON.
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException On malformed JSON.
     */
    public function json(): array
    {
        return json_decode($this->content, true, 512, JSON_THROW_ON_ERROR);
    }
}
