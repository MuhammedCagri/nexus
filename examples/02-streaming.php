<?php

// Streaming responses from LLM providers.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Nexus;

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->create();

echo "Streaming response:\n";

$stream = $nexus->stream('Tell me a short story about a PHP developer who discovered AI.');

$stream
    ->onText(function (string $chunk) {
        echo $chunk;
        flush();
    })
    ->onComplete(function ($response) {
        echo "\n\n--- Stream complete ---\n";
        echo "Finish reason: " . $response->getFinishReason()->value . PHP_EOL;
    })
    ->await();

// Anthropic streaming
$claude = Nexus::using('anthropic', 'claude-sonnet-4-20250514')
    ->withApiKey('YOUR_ANTHROPIC_API_KEY')
    ->create();

echo "\n\nAnthropic streaming:\n";

$stream = $claude->stream('Write a poem about code.');
$stream->onText(fn (string $chunk) => print($chunk))->await();
