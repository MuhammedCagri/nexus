<?php

declare(strict_types=1);

namespace Nexus\Provider;

use Nexus\Contract\ToolInterface;
use Nexus\Enum\FinishReason;
use Nexus\Enum\Role;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;
use Nexus\Message\ToolCall;
use Nexus\Response\Response;
use Nexus\Stream\StreamResponse;

/**
 * Anthropic Messages API provider.
 *
 * @package Nexus\Provider
 */
class Anthropic extends AbstractProvider
{
    private const API_VERSION = '2023-06-01';

    /** @inheritDoc */
    protected function getEndpoint(): string
    {
        return rtrim($this->config->getBaseUrl(), '/') . '/messages';
    }

    /** @inheritDoc */
    protected function getStreamEndpoint(): string
    {
        return $this->getEndpoint();
    }

    /** @inheritDoc */
    protected function getHeaders(): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'x-api-key' => $this->config->apiKey,
            'anthropic-version' => self::API_VERSION,
        ], $this->config->headers);
    }

    /** @inheritDoc */
    protected function buildRequestBody(array $messages, array $options): array
    {
        $systemPrompt = null;
        $filteredMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
                continue;
            }
            $filteredMessages[] = $this->convertMessage($msg);
        }

        $body = [
            'model' => $this->config->model,
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens ?? 4096,
            'temperature' => $options['temperature'] ?? $this->config->temperature,
            'top_p' => $options['top_p'] ?? $this->config->topP,
        ];

        if ($systemPrompt !== null) {
            $body['system'] = $systemPrompt;
        }

        if (!empty($options['tools'])) {
            $body['tools'] = $this->formatTools($options['tools']);
        }

        return array_merge($body, $this->config->extra, $options['extra'] ?? []);
    }

    /**
     * @param MessageBag           $messages Conversation history
     * @param array<string, mixed> $options  Per-request overrides (temperature, tools, etc.)
     * @return Response
     */
    public function chat(MessageBag $messages, array $options = []): Response
    {
        $body = $this->buildRequestBody($messages->toArray(), $options);
        $data = $this->post($this->getEndpoint(), $body);

        return $this->buildResponse($data);
    }

    /**
     * @param MessageBag           $messages Conversation history
     * @param array<string, mixed> $options  Per-request overrides (temperature, tools, etc.)
     * @return StreamResponse
     */
    public function stream(MessageBag $messages, array $options = []): StreamResponse
    {
        $body = $this->buildRequestBody($messages->toArray(), $options);
        $body['stream'] = true;

        $url = $this->getStreamEndpoint();
        $headers = $this->getHeaders();
        $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);

        return new StreamResponse(function (StreamResponse $streamResponse) use ($url, $headers, $encodedBody) {
            $buffer = '';
            $currentToolCall = null;
            $toolCallArgs = '';

            $this->http->stream(
                method: 'POST',
                url: $url,
                headers: $headers,
                body: $encodedBody,
                onChunk: function (string $chunk) use ($streamResponse, &$buffer, &$currentToolCall, &$toolCallArgs) {
                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $line = trim($line);

                        if ($line === '' || str_starts_with($line, 'event:')) {
                            continue;
                        }

                        if (!str_starts_with($line, 'data: ')) {
                            continue;
                        }

                        $json = json_decode(substr($line, 6), true);
                        if ($json === null) {
                            continue;
                        }

                        $type = $json['type'] ?? '';

                        if ($type === 'content_block_start') {
                            $block = $json['content_block'] ?? [];
                            if (($block['type'] ?? '') === 'tool_use') {
                                $currentToolCall = [
                                    'id' => $block['id'] ?? '',
                                    'name' => $block['name'] ?? '',
                                ];
                                $toolCallArgs = '';
                            }
                        }

                        if ($type === 'content_block_delta') {
                            $delta = $json['delta'] ?? [];
                            if (($delta['type'] ?? '') === 'text_delta') {
                                $streamResponse->appendContent($delta['text'] ?? '');
                            }
                            if (($delta['type'] ?? '') === 'input_json_delta') {
                                $toolCallArgs .= $delta['partial_json'] ?? '';
                            }
                        }

                        if ($type === 'content_block_stop' && $currentToolCall !== null) {
                            $args = json_decode($toolCallArgs, true) ?? [];
                            $streamResponse->addToolCall(new ToolCall(
                                $currentToolCall['id'],
                                $currentToolCall['name'],
                                $args,
                            ));
                            $currentToolCall = null;
                            $toolCallArgs = '';
                        }

                        if ($type === 'message_delta') {
                            $stopReason = $json['delta']['stop_reason'] ?? null;
                            $usage = $json['usage'] ?? [];
                            $streamResponse->complete(
                                $this->mapStopReason($stopReason),
                                $usage,
                            );
                        }
                    }
                },
            );
        });
    }

    /** @inheritDoc */
    protected function parseResponse(array $data): array
    {
        return $data;
    }

    /** @inheritDoc */
    protected function parseStreamChunk(string $chunk): array
    {
        return [];
    }

    /** @inheritDoc */
    protected function formatSingleTool(ToolInterface $tool): array
    {
        return [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->parameters(),
        ];
    }

    /**
     * Translate a generic message array into Anthropic's content-block format.
     *
     * @param array<string, mixed> $msg Normalized message
     * @return array<string, mixed> Anthropic-formatted message
     */
    private function convertMessage(array $msg): array
    {
        $role = $msg['role'];

        // Tool results are sent as user messages with tool_result content blocks
        if ($role === 'tool') {
            return [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'tool_result',
                        'tool_use_id' => $msg['tool_call_id'] ?? '',
                        'content' => $msg['content'] ?? '',
                    ],
                ],
            ];
        }

        // Assistant messages carrying tool calls use mixed content blocks
        if ($role === 'assistant' && !empty($msg['tool_calls'])) {
            $content = [];

            if (!empty($msg['content'])) {
                $content[] = ['type' => 'text', 'text' => $msg['content']];
            }

            foreach ($msg['tool_calls'] as $tc) {
                $args = is_string($tc['function']['arguments'] ?? null)
                    ? json_decode($tc['function']['arguments'], true) ?? []
                    : ($tc['function']['arguments'] ?? $tc['arguments'] ?? $tc['input'] ?? []);

                $content[] = [
                    'type' => 'tool_use',
                    'id' => $tc['id'],
                    'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                    'input' => $args,
                ];
            }

            return ['role' => 'assistant', 'content' => $content];
        }

        return [
            'role' => $role,
            'content' => $msg['content'] ?? '',
        ];
    }

    /**
     * Build a Response from the decoded API payload.
     *
     * @param array<string, mixed> $data Raw decoded response
     * @return Response
     */
    private function buildResponse(array $data): Response
    {
        $content = '';
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
            if ($block['type'] === 'tool_use') {
                $toolCalls[] = new ToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'] ?? [],
                );
            }
        }

        $finishReason = $this->mapStopReason($data['stop_reason'] ?? null);

        return new Response(
            content: $content,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            model: $data['model'] ?? $this->config->model,
            meta: ['id' => $data['id'] ?? null],
        );
    }

    /**
     * Map an Anthropic stop_reason to the internal enum.
     *
     * @param string|null $reason
     * @return FinishReason
     */
    private function mapStopReason(?string $reason): FinishReason
    {
        return match ($reason) {
            'end_turn', 'stop' => FinishReason::Stop,
            'tool_use' => FinishReason::ToolCall,
            'max_tokens' => FinishReason::Length,
            default => FinishReason::Unknown,
        };
    }
}
