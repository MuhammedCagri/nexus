<?php

declare(strict_types=1);

namespace Nexus\Provider;

use Nexus\Contract\ToolInterface;
use Nexus\Enum\FinishReason;
use Nexus\Message\MessageBag;
use Nexus\Message\ToolCall;
use Nexus\Response\Response;
use Nexus\Stream\StreamResponse;

/**
 * OpenAI chat completions provider.
 *
 * @package Nexus\Provider
 */
class OpenAI extends AbstractProvider
{
    /** @inheritDoc */
    protected function getEndpoint(): string
    {
        return rtrim($this->config->getBaseUrl(), '/') . '/chat/completions';
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
            'Authorization' => 'Bearer ' . $this->config->apiKey,
        ], $this->config->headers);
    }

    /** @inheritDoc */
    protected function buildRequestBody(array $messages, array $options): array
    {
        $body = [
            'model' => $this->config->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config->temperature,
            'top_p' => $options['top_p'] ?? $this->config->topP,
            'frequency_penalty' => $options['frequency_penalty'] ?? $this->config->frequencyPenalty,
            'presence_penalty' => $options['presence_penalty'] ?? $this->config->presencePenalty,
        ];

        if (($options['max_tokens'] ?? $this->config->maxTokens) !== null) {
            $body['max_tokens'] = $options['max_tokens'] ?? $this->config->maxTokens;
        }

        if (!empty($options['tools'])) {
            $body['tools'] = $this->formatTools($options['tools']);
        }

        if (!empty($options['response_format'])) {
            $body['response_format'] = $options['response_format'];
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
        $body['stream_options'] = ['include_usage' => true];

        $url = $this->getStreamEndpoint();
        $headers = $this->getHeaders();
        $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);

        return new StreamResponse(function (StreamResponse $streamResponse) use ($url, $headers, $encodedBody) {
            $buffer = '';
            $toolCallBuffers = [];

            $this->http->stream(
                method: 'POST',
                url: $url,
                headers: $headers,
                body: $encodedBody,
                onChunk: function (string $chunk) use ($streamResponse, &$buffer, &$toolCallBuffers) {
                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        $line = trim($line);
                        if ($line === '' || $line === 'data: [DONE]') {
                            continue;
                        }

                        if (!str_starts_with($line, 'data: ')) {
                            continue;
                        }

                        $json = json_decode(substr($line, 6), true);
                        if ($json === null) {
                            continue;
                        }

                        $delta = $json['choices'][0]['delta'] ?? [];
                        $finishReason = $json['choices'][0]['finish_reason'] ?? null;

                        if (isset($delta['content'])) {
                            $streamResponse->appendContent($delta['content']);
                        }

                        if (isset($delta['tool_calls'])) {
                            foreach ($delta['tool_calls'] as $tc) {
                                $idx = $tc['index'] ?? 0;
                                if (isset($tc['id'])) {
                                    $toolCallBuffers[$idx] = [
                                        'id' => $tc['id'],
                                        'name' => $tc['function']['name'] ?? '',
                                        'arguments' => '',
                                    ];
                                }
                                if (isset($tc['function']['arguments'])) {
                                    $toolCallBuffers[$idx]['arguments'] .= $tc['function']['arguments'];
                                }
                            }
                        }

                        if ($finishReason !== null) {
                            foreach ($toolCallBuffers as $tcBuf) {
                                $args = json_decode($tcBuf['arguments'], true) ?? [];
                                $streamResponse->addToolCall(new ToolCall($tcBuf['id'], $tcBuf['name'], $args));
                            }

                            $usage = $json['usage'] ?? [];
                            $streamResponse->complete(
                                $this->mapFinishReason($finishReason),
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
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
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
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $toolCalls = [];

        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $toolCalls[] = ToolCall::fromArray($tc);
            }
        }

        $finishReason = $this->mapFinishReason($choice['finish_reason'] ?? 'stop');

        return new Response(
            content: $message['content'] ?? '',
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: $data['usage'] ?? [],
            model: $data['model'] ?? $this->config->model,
            meta: ['id' => $data['id'] ?? null],
        );
    }

    /**
     * Map an OpenAI finish_reason string to the internal enum.
     *
     * @param string $reason
     * @return FinishReason
     */
    private function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'tool_calls' => FinishReason::ToolCall,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };
    }
}
