<?php

declare(strict_types=1);

namespace Nexus\Stream;

use Nexus\Enum\FinishReason;
use Nexus\Message\ToolCall;

/**
 * Lazy, callback-driven wrapper around a streamed LLM completion.
 *
 * @package Nexus\Stream
 */
final class StreamResponse
{
    private string $content = '';
    private ?FinishReason $finishReason = null;
    /** @var ToolCall[] */
    private array $toolCalls = [];
    /** @var array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int} */
    private array $usage = [];
    private bool $started = false;

    /** @var (callable(string): void)|null */
    private $onText;

    /** @var (callable(ToolCall): void)|null */
    private $onToolCall;

    /** @var (callable(self): void)|null */
    private $onComplete;

    /** @var callable(self): void */
    private $executor;

    /**
     * @param callable(self): void $executor Closure that performs HTTP streaming and feeds chunks back.
     */
    public function __construct(callable $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Register a callback invoked for each text chunk.
     *
     * @param callable(string): void $callback
     *
     * @return self
     */
    public function onText(callable $callback): self
    {
        $this->onText = $callback;

        return $this;
    }

    /**
     * Register a callback invoked for each tool call emitted by the model.
     *
     * @param callable(ToolCall): void $callback
     *
     * @return self
     */
    public function onToolCall(callable $callback): self
    {
        $this->onToolCall = $callback;

        return $this;
    }

    /**
     * Register a callback invoked once the stream finishes.
     *
     * @param callable(self): void $callback
     *
     * @return self
     */
    public function onComplete(callable $callback): self
    {
        $this->onComplete = $callback;

        return $this;
    }

    /**
     * Begin consuming the stream. No-op if already started.
     *
     * @return self
     */
    public function start(): self
    {
        if ($this->started) {
            return $this;
        }

        $this->started = true;
        ($this->executor)($this);

        return $this;
    }

    /**
     * Convenience alias for {@see start()}; blocks until the stream completes.
     *
     * @return self
     */
    public function await(): self
    {
        return $this->start();
    }

    /**
     * Append a text chunk and notify the onText listener.
     *
     * @param string $chunk Raw text fragment from the provider.
     */
    public function appendContent(string $chunk): void
    {
        $this->content .= $chunk;

        if ($this->onText !== null) {
            ($this->onText)($chunk);
        }
    }

    /**
     * Record a tool call and notify the onToolCall listener.
     *
     * @param ToolCall $toolCall
     */
    public function addToolCall(ToolCall $toolCall): void
    {
        $this->toolCalls[] = $toolCall;

        if ($this->onToolCall !== null) {
            ($this->onToolCall)($toolCall);
        }
    }

    /**
     * Mark the stream as finished and notify the onComplete listener.
     *
     * @param FinishReason                                                            $reason
     * @param array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int} $usage
     */
    public function complete(FinishReason $reason, array $usage = []): void
    {
        $this->finishReason = $reason;
        $this->usage = $usage;

        if ($this->onComplete !== null) {
            ($this->onComplete)($this);
        }
    }

    /**
     * Accumulated text content. Triggers stream start if not yet started.
     *
     * @return string
     */
    public function getContent(): string
    {
        $this->start();

        return $this->content;
    }

    /**
     * @return FinishReason|null Null until the stream completes.
     */
    public function getFinishReason(): ?FinishReason
    {
        $this->start();

        return $this->finishReason;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls(): array
    {
        $this->start();

        return $this->toolCalls;
    }

    /**
     * @return array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int}
     */
    public function getUsage(): array
    {
        $this->start();

        return $this->usage;
    }
}
