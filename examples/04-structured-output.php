<?php

// Structured output: extract typed data from text.

require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Nexus;

class PersonInfo
{
    public function __construct(
        public readonly string $name = '',
        public readonly int $age = 0,
        public readonly string $city = '',
        public readonly string $occupation = '',
        /** @description List of hobbies */
        public readonly array $hobbies = [],
    ) {
    }
}

class SentimentResult
{
    public function __construct(
        public readonly string $sentiment = '',
        public readonly float $confidence = 0.0,
        public readonly string $explanation = '',
    ) {
    }
}

$nexus = Nexus::using('openai', 'gpt-4o')
    ->withApiKey('YOUR_OPENAI_API_KEY')
    ->create();

// Extract person info
/** @var PersonInfo $person */
$person = $nexus->structured(
    'Extract person info: "Ahmet is a 28-year-old software engineer from Istanbul who enjoys gaming and reading."',
    PersonInfo::class,
);

echo "Name: {$person->name}\n";
echo "Age: {$person->age}\n";
echo "City: {$person->city}\n";
echo "Occupation: {$person->occupation}\n";
echo "Hobbies: " . implode(', ', $person->hobbies) . "\n";

// Sentiment analysis
/** @var SentimentResult $sentiment */
$sentiment = $nexus->structured(
    'Analyze sentiment: "This product is absolutely amazing, best purchase I have ever made!"',
    SentimentResult::class,
);

echo "\nSentiment: {$sentiment->sentiment}\n";
echo "Confidence: {$sentiment->confidence}\n";
echo "Explanation: {$sentiment->explanation}\n";
