<?php

declare(strict_types=1);

namespace Nexus\Contract;

use Nexus\Message\Message;
use Nexus\Message\MessageBag;

/**
 * Conversation memory store for persisting message history.
 *
 * @package Nexus\Contract
 */
interface MemoryInterface
{
    /**
     * Append a message to the conversation history.
     *
     * @param Message $message
     *
     * @return void
     */
    public function add(Message $message): void;

    /**
     * Retrieve the full conversation history.
     *
     * @return MessageBag
     */
    public function messages(): MessageBag;

    /**
     * Purge all stored messages.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Number of messages currently stored.
     *
     * @return int
     */
    public function count(): int;
}
