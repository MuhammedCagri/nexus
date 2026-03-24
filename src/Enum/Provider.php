<?php

declare(strict_types=1);

namespace Nexus\Enum;

/**
 * Supported LLM providers and their API metadata.
 *
 * @package Nexus\Enum
 */
enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Ollama = 'ollama';
    case Groq = 'groq';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case LMStudio = 'lmstudio';
    case Custom = 'custom';

    /**
     * Default API base URL for the provider. Empty string for Custom.
     *
     * @return string
     */
    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::OpenAI => 'https://api.openai.com/v1',
            self::Anthropic => 'https://api.anthropic.com/v1',
            self::Ollama => 'http://localhost:11434',
            self::Groq => 'https://api.groq.com/openai/v1',
            self::DeepSeek => 'https://api.deepseek.com/v1',
            self::Mistral => 'https://api.mistral.ai/v1',
            self::LMStudio => 'http://localhost:1234/v1',
            self::Custom => '',
        };
    }

    /**
     * Whether the provider uses the OpenAI-compatible request/response format.
     *
     * @return bool
     */
    public function usesOpenAIFormat(): bool
    {
        return match ($this) {
            self::OpenAI, self::Groq, self::DeepSeek, self::Mistral, self::LMStudio, self::Custom => true,
            self::Anthropic, self::Ollama => false,
        };
    }
}
