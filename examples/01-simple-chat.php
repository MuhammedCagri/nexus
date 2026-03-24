<?php

// Basic chat usage with different providers.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Nexus;

// OpenAI
$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->create();

$response = $nexus->chat('PHP ile Fibonacci fonksiyonu yaz.');
echo "OpenAI: " . $response->content . PHP_EOL;
echo "Tokens: " . ($response->usage['total_tokens'] ?? 'N/A') . PHP_EOL;

// Anthropic
$claude = Nexus::using('anthropic', 'claude-sonnet-4-20250514')
    ->withApiKey('YOUR_ANTHROPIC_API_KEY')
    ->create();

$response = $claude->chat('Merhaba! Nasılsın?');
echo "\nAnthropic: " . $response->content . PHP_EOL;

// Ollama (local)
$local = Nexus::using('ollama', 'llama3')
    ->create();

$response = $local->chat('What is the meaning of life?');
echo "\nOllama: " . $response->content . PHP_EOL;

// Groq
$groq = Nexus::using('groq', 'llama-3.3-70b-versatile')
    ->withApiKey('YOUR_GROQ_API_KEY')
    ->create();

$response = $groq->chat('Explain quantum computing in one sentence.');
echo "\nGroq: " . $response->content . PHP_EOL;

// DeepSeek
$deepseek = Nexus::using('deepseek', 'deepseek-chat')
    ->withApiKey('YOUR_DEEPSEEK_API_KEY')
    ->create();

$response = $deepseek->chat('Write a haiku about PHP.');
echo "\nDeepSeek: " . $response->content . PHP_EOL;

// LM Studio (local)
$lmstudio = Nexus::using('lmstudio', 'local-model')
    ->withBaseUrl('http://localhost:1234/v1')
    ->create();

$response = $lmstudio->chat('Hello!');
echo "\nLM Studio: " . $response->content . PHP_EOL;

// Custom OpenAI-compatible API
$custom = Nexus::using('custom', 'my-model')
    ->withBaseUrl('https://my-api.example.com/v1')
    ->withApiKey('my-api-key')
    ->create();

$response = $custom->chat('Ping');
echo "\nCustom: " . $response->content . PHP_EOL;
