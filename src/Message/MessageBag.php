<?php

declare(strict_types=1);

namespace Nexus\Message;

use Nexus\Enum\Role;

/**
 * Ordered, mutable collection of Message objects.
 *
 * @package Nexus\Message
 *
 * @implements \IteratorAggregate<int, Message>
 */
final class MessageBag implements \Countable, \IteratorAggregate
{
    /** @var Message[] */
    private array $messages = [];

    public function __construct(Message ...$messages)
    {
        $this->messages = array_values($messages);
    }

    /**
     * Append a message to the end of the bag.
     *
     * @param Message $message
     *
     * @return self
     */
    public function add(Message $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Insert a message at the beginning of the bag.
     *
     * @param Message $message
     *
     * @return self
     */
    public function prepend(Message $message): self
    {
        array_unshift($this->messages, $message);

        return $this;
    }

    /**
     * Return the first system-role message, or null if none exists.
     *
     * @return Message|null
     */
    public function system(): ?Message
    {
        foreach ($this->messages as $message) {
            if ($message->role === Role::System) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Return a new bag with all system-role messages removed.
     *
     * @return self
     */
    public function withoutSystem(): self
    {
        $bag = new self();
        foreach ($this->messages as $message) {
            if ($message->role !== Role::System) {
                $bag->add($message);
            }
        }

        return $bag;
    }

    /**
     * Return the last message, or null if the bag is empty.
     *
     * @return Message|null
     */
    public function last(): ?Message
    {
        return $this->messages !== [] ? $this->messages[array_key_last($this->messages)] : null;
    }

    /**
     * @return Message[]
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * @return \ArrayIterator<int, Message>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->messages);
    }

    /**
     * Serialize all messages to an array of API-compatible associative arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Message $m) => $m->toArray(), $this->messages);
    }

    /**
     * Append all messages from another bag into this one.
     *
     * @param self $other
     *
     * @return self
     */
    public function merge(self $other): self
    {
        foreach ($other->messages as $message) {
            $this->messages[] = $message;
        }

        return $this;
    }

    /**
     * Extract a portion of the bag as a new instance.
     *
     * @param int      $offset Start index.
     * @param int|null $length Maximum number of messages; null for all remaining.
     *
     * @return self
     */
    public function slice(int $offset, ?int $length = null): self
    {
        $bag = new self();
        $bag->messages = array_values(array_slice($this->messages, $offset, $length));

        return $bag;
    }
}
