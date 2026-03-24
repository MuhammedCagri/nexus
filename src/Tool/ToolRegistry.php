<?php

declare(strict_types=1);

namespace Nexus\Tool;

use Nexus\Contract\ToolInterface;
use Nexus\Exception\ToolException;

/**
 * Named registry of tools with lookup and execution support.
 *
 * @package Nexus\Tool
 */
final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /**
     * Register a tool, keyed by its name.
     *
     * @return $this
     */
    public function register(ToolInterface $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /**
     * Register multiple tools at once.
     *
     * @param ToolInterface[] $tools
     *
     * @return $this
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }

        return $this;
    }

    /**
     * Retrieve a tool by name.
     *
     * @throws ToolException If the tool is not registered.
     */
    public function get(string $name): ToolInterface
    {
        return $this->tools[$name] ?? throw new ToolException(
            "Tool '{$name}' not found in registry.",
            toolName: $name,
        );
    }

    /**
     * Check whether a tool is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Return all registered tools (indexed numerically).
     *
     * @return ToolInterface[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Look up a tool by name and execute it.
     *
     * @param string               $name
     * @param array<string, mixed> $arguments
     *
     * @return string Tool output.
     *
     * @throws ToolException If the tool is not found or execution fails.
     */
    public function execute(string $name, array $arguments): string
    {
        $tool = $this->get($name);

        try {
            return $tool->execute($arguments);
        } catch (ToolException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ToolException(
                "Tool '{$name}' execution failed: {$e->getMessage()}",
                toolName: $name,
                previous: $e,
            );
        }
    }

    /**
     * Number of registered tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }
}
