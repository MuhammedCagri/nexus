<?php

declare(strict_types=1);

namespace Nexus\Structured;

use Nexus\Exception\NexusException;

/**
 * Maps PHP classes to JSON Schema and hydrates objects from associative arrays.
 *
 * @package Nexus\Structured
 */
final class SchemaMapper
{
    /**
     * Generate a JSON Schema array from a class's public properties via reflection.
     *
     * @param class-string $class
     *
     * @return array{type: string, properties: array<string, array<string, mixed>>, required: string[]}
     *
     * @throws NexusException If the class does not exist
     */
    public static function fromClass(string $class): array
    {
        if (!class_exists($class)) {
            throw new NexusException("Class '{$class}' does not exist.");
        }

        $ref = new \ReflectionClass($class);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $propSchema = self::mapType($type);

            $doc = $prop->getDocComment();
            if ($doc !== false && preg_match('/@description\s+(.+)/', $doc, $m)) {
                $propSchema['description'] = trim($m[1]);
            }

            $properties[$prop->getName()] = $propSchema;

            if ($type !== null && !$type->allowsNull() && !$prop->hasDefaultValue()) {
                $required[] = $prop->getName();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Hydrate a class instance from an associative array.
     *
     * Uses the constructor if available; otherwise sets public properties directly.
     *
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     */
    public static function hydrate(string $class, array $data): object
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor !== null) {
            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $data)) {
                    $args[] = $data[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $args[] = null;
                }
            }

            return $ref->newInstanceArgs($args);
        }

        $instance = $ref->newInstanceWithoutConstructor();
        foreach ($data as $key => $value) {
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                $prop->setAccessible(true);
                $prop->setValue($instance, $value);
            }
        }

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapType(?\ReflectionType $type): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(
                fn (\ReflectionNamedType $t) => self::mapNamedType($t),
                array_filter($type->getTypes(), fn ($t) => $t instanceof \ReflectionNamedType),
            );

            return ['anyOf' => array_values($types)];
        }

        if ($type instanceof \ReflectionNamedType) {
            $schema = self::mapNamedType($type);
            if ($type->allowsNull()) {
                return ['anyOf' => [$schema, ['type' => 'null']]];
            }

            return $schema;
        }

        return ['type' => 'string'];
    }

    /**
     * @return array{type: string}
     */
    private static function mapNamedType(\ReflectionNamedType $type): array
    {
        return match ($type->getName()) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            'array' => ['type' => 'array'],
            default => ['type' => 'object'],
        };
    }
}
