<?php

// Middleware: retry, caching, logging, and rate limiting.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Contract\MiddlewareInterface;
use Nexus\Message\MessageBag;
use Nexus\Middleware\CacheMiddleware;
use Nexus\Middleware\RetryMiddleware;
use Nexus\Nexus;
use Nexus\Response\Response;

class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(MessageBag $messages, array $options, callable $next): Response
    {
        $start = microtime(true);
        echo "[LOG] Sending {$messages->count()} messages...\n";

        $response = $next($messages, $options);

        $elapsed = round((microtime(true) - $start) * 1000);
        echo "[LOG] Response received in {$elapsed}ms";
        echo " | Tokens: " . ($response->usage['total_tokens'] ?? 'N/A');
        echo " | Finish: " . $response->finishReason->value . "\n";

        return $response;
    }
}

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $lastRequestTime = 0;

    public function __construct(
        private readonly int $minIntervalMs = 100,
    ) {
    }

    public function handle(MessageBag $messages, array $options, callable $next): Response
    {
        $now = (int) (microtime(true) * 1000);
        $elapsed = $now - $this->lastRequestTime;

        if ($elapsed < $this->minIntervalMs && $this->lastRequestTime > 0) {
            usleep(($this->minIntervalMs - $elapsed) * 1000);
        }

        $this->lastRequestTime = (int) (microtime(true) * 1000);

        return $next($messages, $options);
    }
}

// Build with middleware stack
$nexus = Nexus::using('openai', 'gpt-4o-mini')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->create()
    ->withMiddleware(new LoggingMiddleware())
    ->withMiddleware(new RetryMiddleware(maxRetries: 3, baseDelayMs: 500))
    ->withMiddleware(new CacheMiddleware(ttlSeconds: 60))
    ->withMiddleware(new RateLimitMiddleware(minIntervalMs: 200));

// First call hits the API
$response = $nexus->chat('Say hello in 3 languages.');
echo "\n" . $response->content . "\n";

// Second identical call is served from cache
echo "\n--- Second call (cached) ---\n";
$response = $nexus->chat('Say hello in 3 languages.');
echo $response->content . "\n";
