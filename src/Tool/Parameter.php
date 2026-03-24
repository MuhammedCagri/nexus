<?php

declare(strict_types=1);

namespace Nexus\Tool;

/**
 * Value object representing a single tool parameter and its JSON Schema definition.
 *
 * @package Nexus\Tool
 */
final class Parameter
{
    public function __construct(
        public string $name,
        public string $type = 'string',
        public string $description = '',
        public bool $required = true,
        public ?array $enum = null,
        public mixed $default = null,
    ) {
    }

    /**
     * Convert to a JSON Schema property definition.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        return $schema;
    }
}
