<?php

declare(strict_types=1);

namespace Nexus\Enum;

/**
 * Reason the provider stopped generating tokens.
 *
 * @package Nexus\Enum
 */
enum FinishReason: string
{
    case Stop = 'stop';
    case ToolCall = 'tool_call';
    case Length = 'length';
    case ContentFilter = 'content_filter';
    case Error = 'error';
    case Unknown = 'unknown';
}
