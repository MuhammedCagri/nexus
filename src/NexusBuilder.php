<?php

declare(strict_types=1);

namespace Nexus;

use Nexus\Config\ProviderConfig;
use Nexus\Enum\Provider;

/**
 * Fluent builder for configuring and creating a Nexus instance.
 *
 * @package Nexus
 */
final class NexusBuilder
{
    private string $apiKey = '';
    private string $baseUrl = '';
    private float $temperature = 0.7;
    private ?int $maxTokens = null;
    private float $topP = 1.0;
    private float $frequencyPenalty = 0.0;
    private float $presencePenalty = 0.0;
    private ?int $timeout = 30;
    private ?int $connectTimeout = 10;
    private array $headers = [];
    private array $extra = [];

    public function __construct(
        private readonly Provider $provider,
        private readonly string $model,
    ) {
    }

    /**
     * @return $this
     */
    public function withApiKey(string $key): self
    {
        $this->apiKey = $key;

        return $this;
    }

    /**
     * @return $this
     */
    public function withBaseUrl(string $url): self
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * @return $this
     */
    public function withTemperature(float $temp): self
    {
        $this->temperature = $temp;

        return $this;
    }

    /**
     * @return $this
     */
    public function withMaxTokens(int $max): self
    {
        $this->maxTokens = $max;

        return $this;
    }

    /**
     * @return $this
     */
    public function withTopP(float $topP): self
    {
        $this->topP = $topP;

        return $this;
    }

    /**
     * @return $this
     */
    public function withFrequencyPenalty(float $penalty): self
    {
        $this->frequencyPenalty = $penalty;

        return $this;
    }

    /**
     * @return $this
     */
    public function withPresencePenalty(float $penalty): self
    {
        $this->presencePenalty = $penalty;

        return $this;
    }

    /**
     * @param int $seconds HTTP request timeout
     *
     * @return $this
     */
    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * @param int $seconds TCP connect timeout
     *
     * @return $this
     */
    public function withConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    /**
     * @param array<string, string> $headers Additional HTTP headers
     *
     * @return $this
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * @param array<string, mixed> $extra Provider-specific parameters
     *
     * @return $this
     */
    public function withExtra(array $extra): self
    {
        $this->extra = array_merge($this->extra, $extra);

        return $this;
    }

    /**
     * Build the Nexus instance from the accumulated configuration.
     */
    public function create(): Nexus
    {
        $config = new ProviderConfig(
            provider: $this->provider,
            model: $this->model,
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

        return Nexus::fromConfig($config);
    }
}
