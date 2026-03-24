<?php

declare(strict_types=1);

namespace Nexus\Tool\Attribute;

/**
 * Marks a class as a tool, providing its name and description for the LLM.
 *
 * @package Nexus\Tool\Attribute
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsTool
{
    public function __construct(
        public string $name,
        public string $description,
    ) {
    }
}
