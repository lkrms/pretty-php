<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Token flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class TokenFlag extends AbstractEnumeration
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
     * The token is not a tag, comment, whitespace or inline markup
     */
    public const CODE = 16;

    /**
     * The token is a T_CLOSE_BRACE or T_CLOSE_TAG that terminates a statement
     */
    public const STATEMENT_TERMINATOR = 32;

    /**
     * The token is a T_QUESTION or T_COLON belonging to a ternary operator
     */
    public const TERNARY_OPERATOR = 64;

    /**
     * The token is a C-style comment where every line starts with "*" or at
     * least one delimiter appears on its own line
     */
    public const INFORMAL_DOC_COMMENT = 128;

    /**
     * The token is a T_OPEN_BRACE or T_CLOSE_BRACE that delimits a code block
     * or trait adaptation
     *
     * Not applied to braces in:
     *
     * - expressions (e.g. `$object->{$property}`, `match ($value) {}`)
     * - strings (e.g. `"{$object->property}"`)
     * - alias/import statements (e.g. `use A\{B, C}`)
     */
    public const STRUCTURAL_BRACE = 256;

    /**
     * The token is the first in a statement that declares a named entity
     */
    public const NAMED_DECLARATION = 512;

    /**
     * The token is the parent of a list of items
     */
    public const LIST_PARENT = 1024;

    /**
     * The token is a collapsible one-line comment
     */
    public const COLLAPSIBLE_COMMENT = 2048;

    /**
     * The token is a control structure with an unenclosed body
     */
    public const HAS_UNENCLOSED_BODY = 4096;
}
