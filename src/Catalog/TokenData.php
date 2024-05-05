<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Token data types
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class TokenData extends AbstractEnumeration
{
    /**
     * The content of a normalised T_COMMENT or T_DOC_COMMENT after removal of
     * delimiters, trailing whitespace and leading asterisks
     */
    public const COMMENT_CONTENT = 0;

    /**
     * The number of items associated with a LIST_PARENT token
     */
    public const LIST_ITEM_COUNT = 1;

    /**
     * The LIST_PARENT of the first token in a list item
     */
    public const LIST_PARENT = 2;
}
