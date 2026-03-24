<?php

// Agent with tool calling.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Contract\ToolInterface;
use Nexus\Memory\InMemoryStore;
use Nexus\Middleware\RetryMiddleware;
use Nexus\Nexus;

class CalculatorTool implements ToolInterface
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Performs basic arithmetic. Supports +, -, *, / operations.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'A mathematical expression like "2 + 3 * 4"',
                ],
            ],
            'required' => ['expression'],
        ];
    }

    public function execute(array $arguments): string
    {
        $expr = $arguments['expression'];

        if (!preg_match('/^[\d\s\+\-\*\/\.\(\)]+$/', $expr)) {
            return 'Error: Invalid expression. Only numbers and +, -, *, / are allowed.';
        }

        try {
            $result = 0;
            eval('$result = ' . $expr . ';');
            return "Result: " . (string) $result;
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}

class WeatherTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get the current weather for a city.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'The city name (e.g., Istanbul, New York)',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Temperature unit',
                    'enum' => ['celsius', 'fahrenheit'],
                ],
            ],
            'required' => ['city'],
        ];
    }

    public function execute(array $arguments): string
    {
        $city = $arguments['city'];
        $unit = $arguments['unit'] ?? 'celsius';

        // Replace with a real weather API call
        $temp = rand(15, 35);
        if ($unit === 'fahrenheit') {
            $temp = (int) ($temp * 9 / 5 + 32);
        }

        return json_encode([
            'city' => $city,
            'temperature' => $temp,
            'unit' => $unit,
            'condition' => ['sunny', 'cloudy', 'rainy', 'partly cloudy'][rand(0, 3)],
            'humidity' => rand(30, 80) . '%',
        ]);
    }
}

// Create the agent
$provider = Nexus::createProvider(
    new \Nexus\Config\ProviderConfig(
        provider: \Nexus\Enum\Provider::Anthropic,
        model: 'claude-sonnet-4-20250514',
        apiKey: 'YOUR_ANTHROPIC_API_KEY',
    ),
);

$agent = Nexus::agent()
    ->withProvider($provider)
    ->withSystemPrompt('You are a helpful assistant. Use tools when needed to answer questions accurately.')
    ->withTools([
        new CalculatorTool(),
        new WeatherTool(),
    ])
    ->withMemory(new InMemoryStore(maxMessages: 50))
    ->withMiddleware(new RetryMiddleware(maxRetries: 2))
    ->withMaxIterations(5)
    ->withTemperature(0.3)
    ->build();

// The agent will automatically call the calculator tool
$response = $agent->run('What is 1547 * 382 + 99?');
echo "Math: " . $response->content . PHP_EOL;

// The agent will call the weather tool and respond naturally
$response = $agent->run('What is the weather like in Istanbul and Tokyo?');
echo "\nWeather: " . $response->content . PHP_EOL;

// Multi-turn: the agent remembers context
$response = $agent->run('Which of those cities was warmer?');
echo "\nFollow-up: " . $response->content . PHP_EOL;
