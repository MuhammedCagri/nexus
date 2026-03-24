<?php

declare(strict_types=1);

namespace Nexus\Config;

use Nexus\Enum\Provider;

/**
 * Immutable configuration for an LLM provider connection.
 *
 * @package Nexus\Config
 */
final class ProviderConfig
{
    /**
     * @param Provider             $provider         Target LLM provider.
     * @param string               $model            Model identifier (e.g. "gpt-4o").
     * @param string               $baseUrl          Custom API base URL; empty falls back to provider default.
     * @param string               $apiKey           Authentication key.
     * @param float                $temperature      Sampling temperature.
     * @param int|null             $maxTokens        Maximum completion tokens; null for provider default.
     * @param float                $topP             Nucleus sampling threshold.
     * @param float                $frequencyPenalty  Frequency penalty coefficient.
     * @param float                $presencePenalty   Presence penalty coefficient.
     * @param int|null             $timeout          HTTP request timeout in seconds.
     * @param int|null             $connectTimeout   TCP connect timeout in seconds.
     * @param array<string, string> $headers         Additional HTTP headers.
     * @param array<string, mixed>  $extra           Provider-specific parameters.
     */
    public function __construct(
        public readonly Provider $provider,
        public readonly string $model,
        public readonly string $baseUrl = '',
        public readonly string $apiKey = '',
        public readonly float $temperature = 0.7,
        public readonly ?int $maxTokens = null,
        public readonly float $topP = 1.0,
        public readonly float $frequencyPenalty = 0.0,
        public readonly float $presencePenalty = 0.0,
        public readonly ?int $timeout = 30,
        public readonly ?int $connectTimeout = 10,
        public readonly array $headers = [],
        public readonly array $extra = [],
    ) {
    }

    /**
     * Resolve effective base URL, falling back to the provider's default.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl !== '' ? $this->baseUrl : $this->provider->defaultBaseUrl();
    }

    /**
     * Return a copy with a different model.
     *
     * @param string $model
     *
     * @return self
     */
    public function withModel(string $model): self
    {
        return new self(
            provider: $this->provider,
            model: $model,
            baseUrl: $this->baseUrl,
            apiKey: $this->apiKey,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            topP: $this->topP,
            frequencyPenalty: $this->frequencyPenalty,
            presencePenalty: $this->presencePenalty,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            headers: $this->headers,
            extra: $this->extra,
        );
    }

    /**
     * Return a copy with a different sampling temperature.
     *
     * @param float $temperature
     *
     * @return self
     */
    public function withTemperature(float $temperature): self
    {
        return new self(
            provider: $this->provider,
            model: $this->model,
            baseUrl: $this->baseUrl,
            apiKey: $this->apiKey,
            temperature: $temperature,
            maxTokens: $this->maxTokens,
            topP: $this->topP,
            frequencyPenalty: $this->frequencyPenalty,
            presencePenalty: $this->presencePenalty,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            headers: $this->headers,
            extra: $this->extra,
        );
    }

    /**
     * Return a copy with a different max-token limit.
     *
     * @param int|null $maxTokens Null removes the limit.
     *
     * @return self
     */
    public function withMaxTokens(?int $maxTokens): self
    {
        return new self(
            provider: $this->provider,
            model: $this->model,
            baseUrl: $this->baseUrl,
            apiKey: $this->apiKey,
            temperature: $this->temperature,
            maxTokens: $maxTokens,
            topP: $this->topP,
            frequencyPenalty: $this->frequencyPenalty,
            presencePenalty: $this->presencePenalty,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            headers: $this->headers,
            extra: $this->extra,
        );
    }
}
