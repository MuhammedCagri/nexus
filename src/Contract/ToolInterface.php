<?php

declare(strict_types=1);

namespace Nexus\Contract;

/**
 * Callable tool exposed to the LLM during function-calling.
 *
 * @package Nexus\Contract
 */
interface ToolInterface
{
    /**
     * Unique tool identifier sent to the provider.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Human-readable purpose of the tool, included in the prompt.
     *
     * @return string
     */
    public function description(): string;

    /**
     * JSON Schema definition for accepted parameters.
     *
     * @return array{type: string, properties?: array<string,mixed>, required?: list<string>}
     */
    public function parameters(): array;

    /**
     * Run the tool with the given arguments and return a result string.
     *
     * @param array<string,mixed> $arguments Decoded arguments from the LLM
     *
     * @return string
     *
     * @throws \Nexus\Exception\ToolException
     */
    public function execute(array $arguments): string;
}
