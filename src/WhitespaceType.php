<?php

declare(strict_types=1);

namespace Lkrms\Pretty;

use Lkrms\Concept\Enumeration;
use UnexpectedValueException;

final class WhitespaceType extends Enumeration
{
    /**
     * No whitespace
     */
    public const NONE = 0;

    /**
     * Horizontal space
     */
    public const SPACE = 1;

    /**
     * Newline
     */
    public const LINE = 2;

    /**
     * Two newlines
     */
    public const BLANK = 4;

    public static function toWhitespace(int $value): string
    {
        if (!$value)
        {
            return "";
        }
        elseif ($value & self::BLANK)
        {
            return "\n\n";
        }
        elseif ($value & self::LINE)
        {
            return "\n";
        }
        elseif ($value & self::SPACE)
        {
            return " ";
        }
        throw new UnexpectedValueException("Invalid WhitespaceType: $value");
    }

}
