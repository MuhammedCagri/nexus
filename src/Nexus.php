<?php

declare(strict_types=1);

namespace Nexus;

use Nexus\Agent\Agent;
use Nexus\Agent\AgentBuilder;
use Nexus\Config\ProviderConfig;
use Nexus\Contract\MemoryInterface;
use Nexus\Contract\MiddlewareInterface;
use Nexus\Contract\ProviderInterface;
use Nexus\Contract\ToolInterface;
use Nexus\Enum\Provider;
use Nexus\Memory\InMemoryStore;
use Nexus\Message\Message;
use Nexus\Message\MessageBag;
use Nexus\Middleware\Pipeline;
use Nexus\Provider\Anthropic;
use Nexus\Provider\Ollama;
use Nexus\Provider\OpenAI;
use Nexus\Provider\OpenAICompatible;
use Nexus\Prompt\Template;
use Nexus\Response\Response;
use Nexus\Stream\StreamResponse;
use Nexus\Structured\SchemaMapper;

/**
 * Main entry point for the Nexus LLM library.
 *
 * @package Nexus
 */
final class Nexus
{
    private ProviderInterface $provider;
    private Pipeline $pipeline;

    /** @var ToolInterface[] */
    private array $tools = [];

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    private ?string $systemPrompt = null;

    private function __construct(
        private readonly ProviderConfig $config,
    ) {
        $this->provider = self::createProvider($this->config);
        $this->pipeline = new Pipeline();
    }

    // --- Static Factory ---

    /**
     * Start building a Nexus instance for the given provider and model.
     *
     * @param string|Provider $provider Provider name or enum
     * @param string          $model    Model identifier
     */
    public static function using(string|Provider $provider, string $model): NexusBuilder
    {
        $providerEnum = $provider instanceof Provider
            ? $provider
            : Provider::from($provider);

        return new NexusBuilder($providerEnum, $model);
    }

    /**
     * Create a new AgentBuilder.
     */
    public static function agent(): AgentBuilder
    {
        return new AgentBuilder();
    }

    /**
     * Instantiate Nexus from an existing ProviderConfig.
     */
    public static function fromConfig(ProviderConfig $config): self
    {
        return new self($config);
    }

    // --- Configuration ---

    /**
     * @return $this
     */
    public function withSystemPrompt(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * @return $this
     */
    public function withMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        $this->pipeline->pipe($middleware);

        return $this;
    }

    // --- Chat ---

    /**
     * Send a chat request.
     *
     * @param string|MessageBag   $input   User message or pre-built message bag
     * @param array<string, mixed> $options Provider-specific options
     */
    public function chat(string|MessageBag $input, array $options = []): Response
    {
        $messages = $this->resolveMessages($input);

        return $this->pipeline->process(
            $messages,
            $options,
            fn (MessageBag $msgs, array $opts) => $this->provider->chat($msgs, $opts),
        );
    }

    /**
     * Stream a chat response.
     *
     * @param string|MessageBag   $input   User message or pre-built message bag
     * @param array<string, mixed> $options Provider-specific options
     */
    public function stream(string|MessageBag $input, array $options = []): StreamResponse
    {
        $messages = $this->resolveMessages($input);

        return $this->provider->stream($messages, $options);
    }

    // --- Structured Output ---

    /**
     * Get a structured response mapped to the given class.
     *
     * @template T of object
     *
     * @param string               $prompt  User prompt
     * @param class-string<T>      $class   Target DTO class
     * @param array<string, mixed> $options Provider-specific options
     *
     * @return T
     */
    public function structured(string $prompt, string $class, array $options = []): object
    {
        $schema = SchemaMapper::fromClass($class);

        $systemInstruction = "You must respond with valid JSON matching this schema:\n"
            . json_encode($schema, JSON_PRETTY_PRINT) . "\n"
            . "Only output the JSON, nothing else.";

        $messages = new MessageBag(
            Message::system($systemInstruction),
            Message::user($prompt),
        );

        $options['response_format'] = ['type' => 'json_object'];

        $response = $this->chat($messages, $options);
        $data = $response->json();

        return SchemaMapper::hydrate($class, $data);
    }

    // --- Template ---

    /**
     * Render a prompt template and send it as a chat request.
     *
     * @param string               $templateString Template with {{var}} placeholders
     * @param array<string, string> $variables      Substitution values
     * @param array<string, mixed>  $options        Provider-specific options
     */
    public function template(string $templateString, array $variables, array $options = []): Response
    {
        $prompt = (new Template($templateString))->render($variables);

        return $this->chat($prompt, $options);
    }

    // --- Provider Access ---

    /**
     * @return ProviderInterface
     */
    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * @return ProviderConfig
     */
    public function getConfig(): ProviderConfig
    {
        return $this->config;
    }

    // --- Internal ---

    /**
     * @param string|MessageBag $input
     */
    private function resolveMessages(string|MessageBag $input): MessageBag
    {
        if ($input instanceof MessageBag) {
            if ($this->systemPrompt !== null && $input->system() === null) {
                $input->prepend(Message::system($this->systemPrompt));
            }

            return $input;
        }

        $messages = new MessageBag();

        if ($this->systemPrompt !== null) {
            $messages->add(Message::system($this->systemPrompt));
        }

        $messages->add(Message::user($input));

        return $messages;
    }

    /**
     * Resolve a ProviderInterface from the given config.
     */
    public static function createProvider(ProviderConfig $config): ProviderInterface
    {
        return match ($config->provider) {
            Provider::OpenAI => new OpenAI($config),
            Provider::Anthropic => new Anthropic($config),
            Provider::Ollama => new Ollama($config),
            Provider::Groq, Provider::DeepSeek, Provider::Mistral, Provider::LMStudio, Provider::Custom => new OpenAICompatible($config),
        };
    }
}
