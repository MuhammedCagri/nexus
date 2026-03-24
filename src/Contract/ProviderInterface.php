<?php

declare(strict_types=1);

namespace Nexus\Contract;

use Nexus\Message\MessageBag;
use Nexus\Response\Response;
use Nexus\Stream\StreamResponse;

/**
 * LLM provider capable of chat completion and streaming.
 *
 * @package Nexus\Contract
 */
interface ProviderInterface
{
    /**
     * @param MessageBag          $messages Conversation history
     * @param array<string,mixed> $options  Provider-specific parameters (model, temperature, etc.)
     *
     * @return Response
     */
    public function chat(MessageBag $messages, array $options = []): Response;

    /**
     * @param MessageBag          $messages Conversation history
     * @param array<string,mixed> $options  Provider-specific parameters (model, temperature, etc.)
     *
     * @return StreamResponse
     */
    public function stream(MessageBag $messages, array $options = []): StreamResponse;
}
