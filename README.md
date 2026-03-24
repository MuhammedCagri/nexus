# Nexus

Provider-agnostic LLM agent framework for PHP. Works with OpenAI, Anthropic, Ollama, Groq, DeepSeek, Mistral AI, LM Studio, and any OpenAI-compatible API. All providers support tool calling and streaming.

## Requirements

PHP 8.1+, ext-curl, ext-json

## Installation

```bash
composer require nexus-ai/nexus
```

## Quick Start

```php
use Nexus\Nexus;

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey(getenv('OPENAI_API_KEY'))
    ->create();

$response = $nexus->chat('Hello!');
echo $response->content;
```

Switching providers:

```php
$nexus = Nexus::using('anthropic', 'claude-sonnet-4-20250514')
    ->withApiKey(getenv('ANTHROPIC_API_KEY'))
    ->create();

$nexus = Nexus::using('ollama', 'llama3')->create(); // local, no key

$nexus = Nexus::using('custom', 'my-model')
    ->withBaseUrl('https://my-api.example.com/v1')
    ->withApiKey('my-key')
    ->create();
```

## Features

### Streaming

```php
$nexus->stream('Tell me a story.')
    ->onText(fn (string $chunk) => print($chunk))
    ->await();
```

### Agents with Tools

Implement `ToolInterface` (see `examples/03-agent-with-tools.php` for a full example):

```php
$agent = Nexus::agent()
    ->withProvider($provider)
    ->withTools([new CalculatorTool(), new WeatherTool()])
    ->withMemory(new InMemoryStore())
    ->withMaxIterations(10)
    ->build();

$response = $agent->run('What is 1547 * 382?');
```

### Attribute-Based Tools

```php
#[AsTool(name: 'weather', description: 'Get current weather')]
class WeatherTool extends AttributeTool
{
    public function handle(
        #[Param(description: 'City name')] string $city,
    ): string {
        return json_encode(['city' => $city, 'temp' => 22]);
    }
}
```

### Structured Output

Map LLM responses to typed PHP objects:

```php
$person = $nexus->structured('Extract: "Ahmet, 28, Istanbul"', PersonInfo::class);
echo $person->name; // Ahmet
```

### Prompt Templates

```php
$response = $nexus->template(
    'Translate "{{text}}" to {{language}}.',
    ['text' => 'Hello', 'language' => 'French'],
);
```

### Middleware

```php
use Nexus\Middleware\{RetryMiddleware, CacheMiddleware};

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey(getenv('OPENAI_API_KEY'))
    ->create()
    ->withMiddleware(new RetryMiddleware(maxRetries: 3))
    ->withMiddleware(new CacheMiddleware(ttlSeconds: 300));
```

### Memory

```php
use Nexus\Memory\{InMemoryStore, FileStore};

$memory = new InMemoryStore(maxMessages: 50);                        // session-scoped
$memory = new FileStore('/tmp/conversation.json', maxMessages: 100); // persistent
```

## Providers

- **OpenAI** -- GPT-4o, o1, etc.
- **Anthropic** -- Claude 4, Sonnet, Haiku
- **Ollama** -- Llama, Mistral, Gemma (local)
- **Groq** -- Llama, Mixtral
- **DeepSeek**
- **Mistral AI**
- **LM Studio** (local)
- Any **OpenAI-compatible** API

## Architecture

```
src/
├── Nexus.php, NexusBuilder.php
├── Agent/           Agent, AgentBuilder
├── Config/          ProviderConfig
├── Contract/        ProviderInterface, ToolInterface, MemoryInterface, MiddlewareInterface
├── Enum/            Provider, Role, FinishReason
├── Http/            Client (cURL)
├── Memory/          InMemoryStore, FileStore
├── Message/         Message, MessageBag, ToolCall
├── Middleware/       Pipeline, RetryMiddleware, CacheMiddleware
├── Prompt/          Template
├── Provider/        AbstractProvider, OpenAI, Anthropic, Ollama, OpenAICompatible
├── Response/        Response
├── Stream/          StreamResponse
├── Structured/      SchemaMapper
└── Tool/            AttributeTool, Parameter, ToolRegistry, Attribute/{AsTool, Param}
```

## License

MIT
