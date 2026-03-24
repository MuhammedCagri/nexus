<?php

declare(strict_types=1);

namespace Nexus\Exception;

/**
 * Thrown when an LLM provider API call fails.
 *
 * @package Nexus\Exception
 */
class ProviderException extends NexusException
{
    /**
     * @param string                    $message      Error description
     * @param int|null                  $statusCode   HTTP status code, if available
     * @param array<string,mixed>|null  $responseBody Decoded response payload, if available
     * @param \Throwable|null           $previous
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
