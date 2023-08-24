<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Whitespace types applied before and after tokens
 *
 * @api
 *
 * @extends Enumeration<int>
 */
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

    /**
     * All whitespace types
     */
    public const ALL = WhitespaceType::SPACE | WhitespaceType::LINE | WhitespaceType::BLANK;

    public static function toWhitespace(int $value): string
    {
        if ($value & self::BLANK) {
            return "\n\n";
        }
        if ($value & self::LINE) {
            return "\n";
        }
        if ($value & self::SPACE) {
            return ' ';
        }
        return '';
    }
}
