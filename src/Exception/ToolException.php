<?php

declare(strict_types=1);

namespace Nexus\Exception;

/**
 * Thrown when tool execution fails.
 *
 * @package Nexus\Exception
 */
class ToolException extends NexusException
{
    /**
     * @param string          $message  Error description
     * @param string|null     $toolName Identifier of the tool that failed
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        public readonly ?string $toolName = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
