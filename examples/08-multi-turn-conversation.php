<?php

// Multi-turn conversations with memory.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Memory\FileStore;
use Nexus\Memory\InMemoryStore;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;
use Nexus\Nexus;

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->create()
    ->withSystemPrompt('You are a friendly Turkish cooking assistant. Always respond in Turkish.');

// Manual message management
$messages = new MessageBag(
    Message::system('You are an expert PHP developer.'),
    Message::user('What is the difference between abstract class and interface?'),
);

$response = $nexus->chat($messages);
echo "Q1: " . $response->content . "\n\n";

$messages->add(Message::assistant($response->content));
$messages->add(Message::user('Can you show me a code example?'));

$response = $nexus->chat($messages);
echo "Q2: " . $response->content . "\n\n";

// Agent with persistent file-based memory
$provider = Nexus::createProvider(
    new \Nexus\Config\ProviderConfig(
        provider: \Nexus\Enum\Provider::OpenAI,
        model: 'gpt-4o',
        apiKey: 'YOUR_OPENAI_API_KEY',
    ),
);

$agent = Nexus::agent()
    ->withProvider($provider)
    ->withSystemPrompt('You are a helpful coding tutor. Remember what the student has learned.')
    ->withMemory(new FileStore('/tmp/nexus-conversation.json', maxMessages: 100))
    ->build();

$response = $agent->run('Teach me about PHP enums.');
echo "Lesson 1: " . $response->content . "\n\n";

$response = $agent->run('Now show me a practical example using what you just taught.');
echo "Lesson 2: " . $response->content . "\n\n";

$response = $agent->run('Summarize what we covered today.');
echo "Summary: " . $response->content . "\n";
