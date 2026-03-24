<?php

declare(strict_types=1);

namespace Nexus\Tool;

use Nexus\Contract\ToolInterface;
use Nexus\Exception\ToolException;
use Nexus\Tool\Attribute\AsTool;
use Nexus\Tool\Attribute\Param;

/**
 * Abstract base for tools defined via #[AsTool] and #[Param] attributes.
 * Subclasses must declare a handle() method; the parameter schema is derived from its signature.
 *
 * @package Nexus\Tool
 */
abstract class AttributeTool implements ToolInterface
{
    /**
     * @throws ToolException If the subclass does not implement handle().
     */
    public function __construct()
    {
        if (!method_exists($this, 'handle')) {
            throw new ToolException(
                'Class ' . static::class . ' must implement a handle() method.',
                toolName: static::class,
            );
        }
    }

    private ?AsTool $meta = null;

    /** {@inheritdoc} */
    public function name(): string
    {
        return $this->getMeta()->name;
    }

    /** {@inheritdoc} */
    public function description(): string
    {
        return $this->getMeta()->description;
    }

    /**
     * Build JSON Schema parameters from the handle() method signature.
     *
     * @return array{type: string, properties: array<string, mixed>, required: string[]}
     */
    public function parameters(): array
    {
        $ref = new \ReflectionMethod($this, 'handle');
        $properties = [];
        $required = [];

        foreach ($ref->getParameters() as $param) {
            $attr = ($param->getAttributes(Param::class)[0] ?? null)?->newInstance();

            $type = $attr?->type ?: $this->mapPhpType($param->getType());

            $prop = [
                'type' => $type,
                'description' => $attr?->description ?: $param->getName(),
            ];

            if ($attr?->enum !== null) {
                $prop['enum'] = $attr->enum;
            }

            $properties[$param->getName()] = $prop;

            if (!$param->isDefaultValueAvailable()) {
                $required[] = $param->getName();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Unpack arguments and invoke handle().
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments): string
    {
        $ref = new \ReflectionMethod($this, 'handle');
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $arguments)) {
                $args[] = $arguments[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $ref->invokeArgs($this, $args);
    }

    private function getMeta(): AsTool
    {
        if ($this->meta === null) {
            $ref = new \ReflectionClass($this);
            $attrs = $ref->getAttributes(AsTool::class);

            if ($attrs === []) {
                throw new ToolException(
                    'Class ' . static::class . ' must have the #[AsTool] attribute.',
                    toolName: static::class,
                );
            }

            $this->meta = $attrs[0]->newInstance();
        }

        return $this->meta;
    }

    private function mapPhpType(?\ReflectionType $type): string
    {
        if ($type === null || !$type instanceof \ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }
}
