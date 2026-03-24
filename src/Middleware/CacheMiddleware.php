<?php

declare(strict_types=1);

namespace Nexus\Middleware;

use Nexus\Contract\MiddlewareInterface;
use Nexus\Message\MessageBag;
use Nexus\Response\Response;

/**
 * In-memory TTL cache for LLM responses, keyed by message+option hash.
 *
 * @package Nexus\Middleware
 */
final class CacheMiddleware implements MiddlewareInterface
{
    /** @var array<string, array{response: Response, expires: int}> */
    private array $cache = [];

    /**
     * @param int $ttlSeconds Cache entry lifetime in seconds.
     */
    public function __construct(
        private readonly int $ttlSeconds = 300,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Bypass cache by setting `$options['no_cache'] = true`.
     */
    public function handle(MessageBag $messages, array $options, callable $next): Response
    {
        if (!empty($options['no_cache'])) {
            return $next($messages, $options);
        }

        $key = $this->buildKey($messages, $options);
        $now = time();

        if (isset($this->cache[$key]) && $this->cache[$key]['expires'] > $now) {
            return $this->cache[$key]['response'];
        }

        $response = $next($messages, $options);

        $this->cache[$key] = [
            'response' => $response,
            'expires' => $now + $this->ttlSeconds,
        ];

        return $response;
    }

    private function buildKey(MessageBag $messages, array $options): string
    {
        unset($options['no_cache'], $options['tools']);

        return md5(json_encode([
            'messages' => $messages->toArray(),
            'options' => $options,
        ], JSON_THROW_ON_ERROR));
    }
}
