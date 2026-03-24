<?php

declare(strict_types=1);

namespace Nexus\Agent;

use Nexus\Contract\MemoryInterface;
use Nexus\Contract\MiddlewareInterface;
use Nexus\Contract\ProviderInterface;
use Nexus\Contract\ToolInterface;
use Nexus\Memory\InMemoryStore;
use Nexus\Tool\ToolRegistry;

/**
 * Fluent builder for constructing Agent instances.
 *
 * @package Nexus\Agent
 */
final class AgentBuilder
{
    private ?ProviderInterface $provider = null;
    private ?string $systemPrompt = null;
    private ?MemoryInterface $memory = null;
    private int $maxIterations = 10;
    private float $temperature = 0.7;
    private ?int $maxTokens = null;
    private array $extra = [];

    /** @var ToolInterface[] */
    private array $tools = [];

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * @return $this
     */
    public function withProvider(ProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return $this
     */
    public function withSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * @return $this
     */
    public function withMemory(MemoryInterface $memory): self
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * @return $this
     */
    public function withTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;

        return $this;
    }

    /**
     * @param ToolInterface[] $tools
     *
     * @return $this
     */
    public function withTools(array $tools): self
    {
        $this->tools = array_merge($this->tools, $tools);

        return $this;
    }

    /**
     * @return $this
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * @return $this
     */
    public function withMaxIterations(int $max): self
    {
        $this->maxIterations = $max;

        return $this;
    }

    /**
     * @return $this
     */
    public function withTemperature(float $temp): self
    {
        $this->temperature = $temp;

        return $this;
    }

    /**
     * @return $this
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return $this
     */
    public function withExtra(array $extra): self
    {
        $this->extra = array_merge($this->extra, $extra);

        return $this;
    }

    /**
     * Build the Agent instance.
     *
     * @throws \InvalidArgumentException If no provider has been set
     */
    public function build(): Agent
    {
        if ($this->provider === null) {
            throw new \InvalidArgumentException('Provider is required. Call withProvider() first.');
        }

        $registry = new ToolRegistry();
        $registry->registerMany($this->tools);

        return new Agent(
            provider: $this->provider,
            tools: $registry,
            memory: $this->memory ?? new InMemoryStore(),
            systemPrompt: $this->systemPrompt,
            maxIterations: $this->maxIterations,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            middlewares: $this->middlewares,
            extra: $this->extra,
        );
    }
}
