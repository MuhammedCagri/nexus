<?php

declare(strict_types=1);

namespace Nexus\Tool\Attribute;

/**
 * Annotates a handle() parameter with schema metadata (description, type override, enum).
 *
 * @package Nexus\Tool\Attribute
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class Param
{
    public function __construct(
        public string $description = '',
        public string $type = '',
        public ?array $enum = null,
    ) {
    }
}
