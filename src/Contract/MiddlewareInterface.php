<?php

declare(strict_types=1);

namespace Nexus\Contract;

use Nexus\Message\MessageBag;
use Nexus\Response\Response;

/**
 * Pipeline middleware that wraps provider chat calls.
 *
 * @package Nexus\Contract
 */
interface MiddlewareInterface
{
    /**
     * Intercept or transform the request/response in the middleware pipeline.
     *
     * @param MessageBag                                    $messages
     * @param array<string,mixed>                           $options
     * @param callable(MessageBag, array<string,mixed>): Response $next
     *
     * @return Response
     */
    public function handle(MessageBag $messages, array $options, callable $next): Response;
}
