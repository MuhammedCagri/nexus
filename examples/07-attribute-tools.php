<?php

// Attribute-based tool definitions.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Memory\InMemoryStore;
use Nexus\Nexus;
use Nexus\Tool\Attribute\AsTool;
use Nexus\Tool\Attribute\Param;
use Nexus\Tool\AttributeTool;

#[AsTool(name: 'search_database', description: 'Search for records in the database by query string')]
class DatabaseSearchTool extends AttributeTool
{
    public function handle(
        #[Param(description: 'The search query string')] string $query,
        #[Param(description: 'Maximum number of results to return')] int $limit = 10,
    ): string {
        // Replace with actual database logic
        return json_encode([
            'results' => [
                ['id' => 1, 'title' => "Result for: {$query}", 'score' => 0.95],
                ['id' => 2, 'title' => "Another match: {$query}", 'score' => 0.87],
            ],
            'total' => 2,
            'query' => $query,
            'limit' => $limit,
        ]);
    }
}

#[AsTool(name: 'send_email', description: 'Send an email to a recipient')]
class SendEmailTool extends AttributeTool
{
    public function handle(
        #[Param(description: 'Recipient email address')] string $to,
        #[Param(description: 'Email subject line')] string $subject,
        #[Param(description: 'Email body content')] string $body,
    ): string {
        // Replace with actual email sending logic
        return "Email sent successfully to {$to} with subject: {$subject}";
    }
}

$provider = Nexus::createProvider(
    new \Nexus\Config\ProviderConfig(
        provider: \Nexus\Enum\Provider::OpenAI,
        model: 'gpt-4o',
        apiKey: 'YOUR_OPENAI_API_KEY',
    ),
);

$dbTool = new DatabaseSearchTool();

// Auto-generated schema from attributes
echo "Tool: " . $dbTool->name() . PHP_EOL;
echo "Description: " . $dbTool->description() . PHP_EOL;
echo "Parameters: " . json_encode($dbTool->parameters(), JSON_PRETTY_PRINT) . PHP_EOL;

// Build agent with attribute-based tools
$agent = Nexus::agent()
    ->withProvider($provider)
    ->withSystemPrompt('You are an assistant with database and email capabilities.')
    ->withTools([
        new DatabaseSearchTool(),
        new SendEmailTool(),
    ])
    ->withMemory(new InMemoryStore())
    ->build();

$response = $agent->run('Search the database for "PHP frameworks" and email the results to dev@example.com');
echo "\n" . $response->content . PHP_EOL;
