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
 * Ollama local inference provider.
 *
 * @package Nexus\Provider
 */
class Ollama extends AbstractProvider
{
    /** @inheritDoc */
    protected function getEndpoint(): string
    {
        return rtrim($this->config->getBaseUrl(), '/') . '/api/chat';
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
        ], $this->config->headers);
    }

    /** @inheritDoc */
    protected function buildRequestBody(array $messages, array $options): array
    {
        $body = [
            'model' => $this->config->model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? $this->config->temperature,
                'top_p' => $options['top_p'] ?? $this->config->topP,
            ],
        ];

        if (($options['max_tokens'] ?? $this->config->maxTokens) !== null) {
            $body['options']['num_predict'] = $options['max_tokens'] ?? $this->config->maxTokens;
        }

        if (!empty($options['tools'])) {
            $body['tools'] = $this->formatTools($options['tools']);
        }

        if (!empty($options['response_format'])) {
            $body['format'] = $options['response_format'];
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
        $body['stream'] = false;
        $data = $this->post($this->getEndpoint(), $body, timeout: 120);

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

            $this->http->stream(
                method: 'POST',
                url: $url,
                headers: $headers,
                body: $encodedBody,
                onChunk: function (string $chunk) use ($streamResponse, &$buffer) {
                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $line = trim($line);

                        if ($line === '') {
                            continue;
                        }

                        $json = json_decode($line, true);
                        if ($json === null) {
                            continue;
                        }

                        $message = $json['message'] ?? [];
                        $done = $json['done'] ?? false;

                        if (!empty($message['content'])) {
                            $streamResponse->appendContent($message['content']);
                        }

                        if (!empty($message['tool_calls'])) {
                            foreach ($message['tool_calls'] as $tc) {
                                $streamResponse->addToolCall(new ToolCall(
                                    id: 'ollama_' . bin2hex(random_bytes(8)),
                                    name: $tc['function']['name'] ?? '',
                                    arguments: $tc['function']['arguments'] ?? [],
                                ));
                            }
                        }

                        if ($done) {
                            $finishReason = !empty($message['tool_calls'])
                                ? FinishReason::ToolCall
                                : FinishReason::Stop;

                            $streamResponse->complete($finishReason, [
                                'prompt_tokens' => $json['prompt_eval_count'] ?? 0,
                                'completion_tokens' => $json['eval_count'] ?? 0,
                                'total_tokens' => ($json['prompt_eval_count'] ?? 0) + ($json['eval_count'] ?? 0),
                            ]);
                        }
                    }
                },
                timeout: 300,
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
     * Build a Response from the decoded Ollama payload.
     *
     * Ollama does not provide tool-call IDs, so synthetic IDs are generated.
     *
     * @param array<string, mixed> $data Raw decoded response
     * @return Response
     */
    private function buildResponse(array $data): Response
    {
        $message = $data['message'] ?? [];
        $toolCalls = [];

        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $toolCalls[] = new ToolCall(
                    id: 'ollama_' . bin2hex(random_bytes(8)),
                    name: $tc['function']['name'] ?? '',
                    arguments: $tc['function']['arguments'] ?? [],
                );
            }
        }

        $finishReason = $toolCalls !== [] ? FinishReason::ToolCall : FinishReason::Stop;

        return new Response(
            content: $message['content'] ?? '',
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: [
                'prompt_tokens' => $data['prompt_eval_count'] ?? 0,
                'completion_tokens' => $data['eval_count'] ?? 0,
                'total_tokens' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0),
            ],
            model: $data['model'] ?? $this->config->model,
        );
    }
}
