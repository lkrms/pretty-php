<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Whitespace types applied before and after tokens
 *
 * @api
 */
interface WhitespaceType
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
    public const ALL =
        WhitespaceType::SPACE
        | WhitespaceType::LINE
        | WhitespaceType::BLANK;
}
