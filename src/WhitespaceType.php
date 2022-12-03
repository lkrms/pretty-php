<?php declare(strict_types=1);

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
     * Four or more horizontal spaces
     */
    public const TAB = 2;

    /**
     * Newline
     */
    public const LINE = 4;

    /**
     * Two newlines
     */
    public const BLANK = 8;

    /**
     * All whitespace types
     */
    public const ALL = WhitespaceType::SPACE | WhitespaceType::TAB | WhitespaceType::LINE | WhitespaceType::BLANK;

    public static function toWhitespace(int $value): string
    {
        if (!$value) {
            return "";
        }
        if ($value & self::BLANK) {
            return "\n\n";
        }
        if ($value & self::LINE) {
            return "\n";
        }
        if ($value & self::TAB) {
            return "    ";
        }
        if ($value & self::SPACE) {
            return " ";
        }
        throw new UnexpectedValueException("Invalid WhitespaceType: $value");
    }
}
