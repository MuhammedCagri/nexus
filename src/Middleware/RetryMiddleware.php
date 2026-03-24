<?php

declare(strict_types=1);

namespace Nexus\Middleware;

use Nexus\Contract\MiddlewareInterface;
use Nexus\Exception\ProviderException;
use Nexus\Message\MessageBag;
use Nexus\Response\Response;

/**
 * Retries failed provider requests with exponential back-off and jitter.
 *
 * @package Nexus\Middleware
 */
final class RetryMiddleware implements MiddlewareInterface
{
    /**
     * @param int   $maxRetries           Maximum number of retry attempts.
     * @param int   $baseDelayMs          Initial delay in milliseconds.
     * @param float $multiplier           Exponential back-off multiplier.
     * @param int[] $retryableStatusCodes HTTP status codes eligible for retry.
     */
    public function __construct(
        private readonly int $maxRetries = 3,
        private readonly int $baseDelayMs = 500,
        private readonly float $multiplier = 2.0,
        private readonly array $retryableStatusCodes = [429, 500, 502, 503, 504],
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws ProviderException When retries are exhausted or the error is non-retryable.
     */
    public function handle(MessageBag $messages, array $options, callable $next): Response
    {
        $attempt = 0;

        while (true) {
            try {
                return $next($messages, $options);
            } catch (ProviderException $e) {
                $attempt++;

                if ($attempt >= $this->maxRetries || !$this->isRetryable($e)) {
                    throw $e;
                }

                $delay = (int) ($this->baseDelayMs * ($this->multiplier ** ($attempt - 1)));
                // Add jitter: ±25%
                $jitter = (int) ($delay * 0.25);
                $delay = $delay + random_int(-$jitter, $jitter);

                usleep($delay * 1000);
            }
        }
    }

    private function isRetryable(ProviderException $e): bool
    {
        if ($e->statusCode === null) {
            return true; // Network errors are retryable
        }

        return in_array($e->statusCode, $this->retryableStatusCodes, true);
    }
}
