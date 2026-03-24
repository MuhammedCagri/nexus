<?php

declare(strict_types=1);

namespace Nexus\Memory;

use Nexus\Contract\MemoryInterface;
use Nexus\Enum\Role;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;
use Nexus\Message\ToolCall;

/**
 * JSON-file-backed conversation memory with optional size limit.
 *
 * @package Nexus\Memory
 */
final class FileStore implements MemoryInterface
{
    private MessageBag $messages;

    /**
     * @param string   $filePath    Path to the JSON persistence file.
     * @param int|null $maxMessages Maximum retained messages (null = unlimited).
     */
    public function __construct(
        private readonly string $filePath,
        private readonly ?int $maxMessages = null,
    ) {
        $this->messages = new MessageBag();
        $this->load();
    }

    /** {@inheritdoc} */
    public function add(Message $message): void
    {
        $this->messages->add($message);
        $this->trim();
        $this->persist();
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
        $this->persist();
    }

    /** {@inheritdoc} */
    public function count(): int
    {
        return $this->messages->count();
    }

    /** Hydrate messages from the JSON file, if it exists. */
    private function load(): void
    {
        if (!file_exists($this->filePath)) {
            return;
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $entry) {
            $toolCalls = [];
            foreach ($entry['tool_calls'] ?? [] as $tc) {
                $toolCalls[] = ToolCall::fromArray($tc);
            }

            $this->messages->add(new Message(
                role: Role::from($entry['role']),
                content: $entry['content'] ?? '',
                toolCalls: $toolCalls,
                toolCallId: $entry['tool_call_id'] ?? null,
                name: $entry['name'] ?? null,
            ));
        }
    }

    /** Write current messages to disk (atomic via LOCK_EX). */
    private function persist(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($this->messages->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
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
