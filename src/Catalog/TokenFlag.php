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
}
