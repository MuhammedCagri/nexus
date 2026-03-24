<?php

declare(strict_types=1);

namespace Nexus\Agent;

use Nexus\Contract\MemoryInterface;
use Nexus\Contract\MiddlewareInterface;
use Nexus\Contract\ProviderInterface;
use Nexus\Enum\FinishReason;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;
use Nexus\Middleware\Pipeline;
use Nexus\Response\Response;
use Nexus\Stream\StreamResponse;
use Nexus\Tool\ToolRegistry;

/**
 * Autonomous agent that loops over tool calls until a final response is produced.
 *
 * @package Nexus\Agent
 */
final class Agent
{
    private readonly Pipeline $pipeline;

    /**
     * @param MiddlewareInterface[] $middlewares
     * @param array<string, mixed>  $extra
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ToolRegistry $tools,
        private readonly MemoryInterface $memory,
        private readonly ?string $systemPrompt = null,
        private readonly int $maxIterations = 10,
        private readonly float $temperature = 0.7,
        private readonly ?int $maxTokens = null,
        array $middlewares = [],
        private readonly array $extra = [],
    ) {
        $this->pipeline = new Pipeline();
        foreach ($middlewares as $middleware) {
            $this->pipeline->pipe($middleware);
        }

        if ($this->systemPrompt !== null && $this->memory->count() === 0) {
            $this->memory->add(Message::system($this->systemPrompt));
        }
    }

    /**
     * Execute the agent loop: send messages, execute tool calls, repeat
     * until a text response is produced or maxIterations is reached.
     *
     * @param string $input User message
     *
     * @return Response Final assistant response
     */
    public function run(string $input): Response
    {
        $this->memory->add(Message::user($input));

        $iteration = 0;

        while ($iteration < $this->maxIterations) {
            $iteration++;

            $response = $this->chat($this->memory->messages());

            $this->memory->add(
                Message::assistant($response->content, $response->toolCalls),
            );

            if (!$response->hasToolCalls()) {
                return $response;
            }

            foreach ($response->toolCalls as $toolCall) {
                $result = $this->tools->execute($toolCall->name, $toolCall->arguments);

                $this->memory->add(
                    Message::tool($result, $toolCall->id, $toolCall->name),
                );
            }
        }

        return $this->chat($this->memory->messages());
    }

    /**
     * Single chat request without the agent loop.
     *
     * @param MessageBag    $messages Conversation messages
     * @param array<string, mixed> $options  Provider options (built internally)
     *
     * @return Response
     */
    public function chat(MessageBag $messages): Response
    {
        $options = [
            'temperature' => $this->temperature,
            'tools' => $this->tools->all(),
            'extra' => $this->extra,
        ];

        if ($this->maxTokens !== null) {
            $options['max_tokens'] = $this->maxTokens;
        }

        return $this->pipeline->process(
            $messages,
            $options,
            fn (MessageBag $msgs, array $opts) => $this->provider->chat($msgs, $opts),
        );
    }

    /**
     * Stream a response for the given user input.
     *
     * @param string $input User message
     *
     * @return StreamResponse
     */
    public function stream(string $input): StreamResponse
    {
        $this->memory->add(Message::user($input));

        $options = [
            'temperature' => $this->temperature,
            'tools' => $this->tools->all(),
            'extra' => $this->extra,
        ];

        if ($this->maxTokens !== null) {
            $options['max_tokens'] = $this->maxTokens;
        }

        return $this->provider->stream($this->memory->messages(), $options);
    }

    /**
     * Append a message directly to the agent's memory.
     *
     * @return $this
     */
    public function addMessage(Message $message): self
    {
        $this->memory->add($message);

        return $this;
    }

    /**
     * @return MemoryInterface
     */
    public function getMemory(): MemoryInterface
    {
        return $this->memory;
    }

    /**
     * @return ToolRegistry
     */
    public function getTools(): ToolRegistry
    {
        return $this->tools;
    }

    /**
     * Clear conversation memory and re-inject the system prompt if set.
     *
     * @return $this
     */
    public function clearMemory(): self
    {
        $this->memory->clear();

        if ($this->systemPrompt !== null) {
            $this->memory->add(Message::system($this->systemPrompt));
        }

        return $this;
    }
}
