<?php

declare(strict_types=1);

namespace Nexus\Enum;

/**
 * Conversation message roles.
 *
 * @package Nexus\Enum
 */
enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
