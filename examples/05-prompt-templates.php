<?php

// Prompt templates with variable substitution.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Nexus;
use Nexus\Prompt\Template;

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->withTemperature(0.5)
    ->create();

// Using the template() shortcut
$response = $nexus->template(
    'Translate the following text to {{language}}: "{{text}}"',
    [
        'language' => 'French',
        'text' => 'Hello, how are you today?',
    ],
);

echo "Translation: " . $response->content . PHP_EOL;

// Using the Template class directly
$codeReview = new Template(
    'You are a senior {{language}} developer. Review this code and provide feedback:

```{{language}}
{{code}}
```

Focus on: {{focus_areas}}
Provide your review in {{output_language}}.'
);

echo "\nTemplate variables: " . implode(', ', $codeReview->variables()) . PHP_EOL;

$prompt = $codeReview->render([
    'language' => 'PHP',
    'code' => 'function add($a, $b) { return $a + $b; }',
    'focus_areas' => 'type safety, error handling, best practices',
    'output_language' => 'Turkish',
]);

$response = $nexus->chat($prompt);
echo "\nCode Review:\n" . $response->content . PHP_EOL;

// Reusable template
$summarizer = new Template(
    'Summarize the following {{content_type}} in {{max_sentences}} sentences. Language: {{language}}.

{{content}}'
);

$response = $nexus->chat($summarizer->render([
    'content_type' => 'article',
    'max_sentences' => '3',
    'language' => 'Turkish',
    'content' => 'PHP 8.3 introduces several new features including typed class constants, the json_validate function, and improvements to the readonly properties. The release also includes performance optimizations that make PHP even faster than previous versions.',
]));

echo "\nSummary: " . $response->content . PHP_EOL;
