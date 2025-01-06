<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token flags
 *
 * @api
 */
interface TokenFlag
{
    /**
     * The token is a C++- or shell-style comment
     */
    public const ONELINE_COMMENT = 4;

    /**
     * The token is a C-style comment or T_DOC_COMMENT
     */
    public const MULTILINE_COMMENT = 8;

    /**
     * The token is a C++-style comment ("//")
     */
    public const CPP_COMMENT = 5;

    /**
     * The token is a shell-style comment ("#")
     */
    public const SHELL_COMMENT = 6;

    /**
     * The token is a C-style comment ("/*")
     */
    public const C_COMMENT = 9;

    /**
     * The token is a T_DOC_COMMENT ("/**")
     */
    public const DOC_COMMENT = 10;

    /**
     * The token is a C-style comment where every line starts with "*" or at
     * least one delimiter appears on its own line
     */
    public const C_DOC_COMMENT = 16;

    /**
     * The token is a collapsible one-line T_DOC_COMMENT
     */
    public const COLLAPSIBLE_COMMENT = 32;

    /**
     * The token is not a tag, comment, whitespace or inline markup
     */
    public const CODE = 64;

    /**
     * The token is a T_CLOSE_BRACE or T_CLOSE_TAG at the end of a statement
     */
    public const TERMINATOR = 128;

    /**
     * The token is a brace that delimits a code block or trait adaptation
     *
     * Not applied to braces in:
     *
     * - expressions (e.g. `$object->{$property}`, `match ($value) {}`)
     * - strings (e.g. `"{$object->property}"`)
     * - alias/import statements (e.g. `use A\{B, C}`)
     */
    public const STRUCTURAL_BRACE = 256;

    /**
     * The token is a T_QUESTION or T_COLON in a ternary expression
     */
    public const TERNARY = 512;

    /**
     * The token is a T_DOUBLE_ARROW in an arrow function
     */
    public const FN_DOUBLE_ARROW = 1024;

    /**
     * The token is the first in a non-anonymous declaration
     */
    public const DECLARATION = 2048;

    /**
     * The token is a control structure with an unenclosed body
     */
    public const UNENCLOSED_PARENT = 4096;

    /**
     * The token is the parent of a list of items
     */
    public const LIST_PARENT = 8192;

    /**
     * The token is the first in a list item
     */
    public const LIST_ITEM = 16384;
}
