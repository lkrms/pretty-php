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
     * The token is a T_CLOSE_BRACE or T_CLOSE_TAG that terminates a statement
     */
    public const STATEMENT_TERMINATOR = 1;

    /**
     * The token is a T_QUESTION or T_COLON belonging to a ternary operator
     */
    public const TERNARY_OPERATOR = 2;

    /**
     * The token is a C-style comment where every line starts with "*" or at
     * least one delimiter appears on its own line
     */
    public const INFORMAL_DOC_COMMENT = 8;

    /**
     * The token is the first in a statement that declares a named entity
     */
    public const NAMED_DECLARATION = 16;
}
