<?php

declare(strict_types=1);

namespace Nexus\Memory;

use Nexus\Contract\MemoryInterface;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;

/**
 * In-process conversation memory with optional size limit.
 *
 * @package Nexus\Memory
 */
final class InMemoryStore implements MemoryInterface
{
    private MessageBag $messages;

    /**
     * @param int|null $maxMessages Maximum retained messages (null = unlimited).
     */
    public function __construct(
        private readonly ?int $maxMessages = null,
    ) {
        $this->messages = new MessageBag();
    }

    /** {@inheritdoc} */
    public function add(Message $message): void
    {
        $this->messages->add($message);
        $this->trim();
    }

    /** {@inheritdoc} */
    public function messages(): MessageBag
    {
        return $this->messages;
    }

    /** {@inheritdoc} */
    public function clear(): void
    {
        $this->messages = new MessageBag();
    }

    /** {@inheritdoc} */
    public function count(): int
    {
        return $this->messages->count();
    }

    /** Drop oldest non-system messages when the cap is exceeded. */
    private function trim(): void
    {
        if ($this->maxMessages === null || $this->messages->count() <= $this->maxMessages) {
            return;
        }

        $system = $this->messages->system();
        $nonSystem = $this->messages->withoutSystem();
        $overflow = $nonSystem->count() - $this->maxMessages + ($system !== null ? 1 : 0);

        if ($overflow <= 0) {
            return;
        }

        $trimmed = $nonSystem->slice($overflow);
        $this->messages = new MessageBag();

        if ($system !== null) {
            $this->messages->add($system);
        }

        $this->messages->merge($trimmed);
    }
}
