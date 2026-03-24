<?php

declare(strict_types=1);

namespace Nexus\Prompt;

/**
 * Simple string template with {{variable}} placeholder substitution.
 *
 * @package Nexus\Prompt
 */
final class Template
{
    private string $template;

    public function __construct(string $template)
    {
        $this->template = $template;
    }

    /**
     * Render the template by replacing {{key}} placeholders with values.
     *
     * @param array<string, string> $variables
     */
    public function render(array $variables = []): string
    {
        $result = $this->template;

        foreach ($variables as $key => $value) {
            $result = str_replace('{{' . $key . '}}', $value, $result);
        }

        return $result;
    }

    /**
     * Extract all placeholder names from the template.
     *
     * @return string[]
     */
    public function variables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->template, $matches);

        return array_unique($matches[1]);
    }

    public function __toString(): string
    {
        return $this->template;
    }
}
