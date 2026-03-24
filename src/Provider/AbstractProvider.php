<?php

declare(strict_types=1);

namespace Nexus\Provider;

use Nexus\Config\ProviderConfig;
use Nexus\Contract\ProviderInterface;
use Nexus\Contract\ToolInterface;
use Nexus\Exception\ProviderException;
use Nexus\Http\Client;

/**
 * Base provider with shared HTTP and tool-formatting logic.
 *
 * @package Nexus\Provider
 */
abstract class AbstractProvider implements ProviderInterface
{
    protected Client $http;

    /**
     * @param ProviderConfig $config Provider configuration (model, keys, timeouts, etc.)
     * @param Client|null    $http   Optional HTTP client override for testing
     */
    public function __construct(
        protected readonly ProviderConfig $config,
        ?Client $http = null,
    ) {
        $this->http = $http ?? new Client(
            timeout: $config->timeout ?? 30,
            connectTimeout: $config->connectTimeout ?? 10,
        );
    }

    /**
     * Chat completions endpoint URL.
     *
     * @return string
     */
    abstract protected function getEndpoint(): string;

    /**
     * Streaming endpoint URL.
     *
     * @return string
     */
    abstract protected function getStreamEndpoint(): string;

    /**
     * HTTP headers required by the provider.
     *
     * @return array<string, string>
     */
    abstract protected function getHeaders(): array;

    /**
     * Build the provider-specific request payload.
     *
     * @param array<int, array<string, mixed>> $messages Normalized message array
     * @param array<string, mixed>             $options  Per-request overrides
     * @return array<string, mixed>
     */
    abstract protected function buildRequestBody(array $messages, array $options): array;

    /**
     * Parse the raw API response into a normalized structure.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    abstract protected function parseResponse(array $data): array;

    /**
     * Parse a single SSE chunk during streaming.
     *
     * @param string $chunk Raw SSE line
     * @return array<string, mixed>
     */
    abstract protected function parseStreamChunk(string $chunk): array;

    /**
     * Send a POST request and decode the JSON response.
     *
     * @param string   $url     Fully-qualified endpoint URL
     * @param array<string, mixed> $body    Request payload
     * @param int|null $timeout Per-request timeout override in seconds
     * @return array<string, mixed> Decoded response body
     *
     * @throws ProviderException On HTTP 4xx/5xx responses
     * @throws \JsonException    On JSON encode/decode failure
     */
    protected function post(string $url, array $body, ?int $timeout = null): array
    {
        $response = $this->http->request(
            method: 'POST',
            url: $url,
            headers: $this->getHeaders(),
            body: json_encode($body, JSON_THROW_ON_ERROR),
            timeout: $timeout,
        );

        if ($response['status'] >= 400) {
            $decoded = json_decode($response['body'], true) ?? [];
            throw new ProviderException(
                message: $decoded['error']['message']
                    ?? $decoded['error']['type']
                    ?? $decoded['message']
                    ?? "HTTP {$response['status']} error",
                statusCode: $response['status'],
                responseBody: $decoded,
            );
        }

        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Convert an array of tools to the provider-specific format.
     *
     * @param ToolInterface[] $tools
     * @return array<int, array<string, mixed>>
     */
    protected function formatTools(array $tools): array
    {
        return array_map(fn (ToolInterface $tool) => $this->formatSingleTool($tool), $tools);
    }

    /**
     * Format a single tool definition for the provider's API schema.
     *
     * @param ToolInterface $tool
     * @return array<string, mixed>
     */
    abstract protected function formatSingleTool(ToolInterface $tool): array;
}
