<?php

declare(strict_types=1);

namespace Nexus\Middleware;

use Nexus\Contract\MiddlewareInterface;
use Nexus\Message\MessageBag;
use Nexus\Response\Response;

/**
 * Composable middleware pipeline using an onion-style execution chain.
 *
 * @package Nexus\Middleware
 */
final class Pipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * Append a middleware to the pipeline.
     *
     * @return $this
     */
    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Run the middleware stack around the given core handler.
     *
     * @param MessageBag                             $messages
     * @param array<string, mixed>                   $options
     * @param callable(MessageBag, array): Response   $core
     */
    public function process(MessageBag $messages, array $options, callable $core): Response
    {
        $chain = array_reduce(
            array_reverse($this->middlewares),
            fn (callable $next, MiddlewareInterface $middleware) => fn (MessageBag $msgs, array $opts) => $middleware->handle($msgs, $opts, $next),
            $core,
        );

        return $chain($messages, $options);
    }
}
