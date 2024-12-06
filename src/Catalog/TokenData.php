<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token data types
 *
 * @api
 */
interface TokenData
{
    /**
     * The content of a normalised T_COMMENT or T_DOC_COMMENT after removal of
     * delimiters, trailing whitespace and leading asterisks
     */
    public const COMMENT_CONTENT = 0;

    /**
     * A collection of items associated with a LIST_PARENT token
     */
    public const LIST_ITEMS = 1;

    /**
     * The number of items associated with a LIST_PARENT token
     */
    public const LIST_ITEM_COUNT = 2;

    /**
     * The LIST_PARENT of the first token in a LIST_ITEM
     */
    public const LIST_PARENT = 3;

    /**
     * The T_COLON or T_QUESTION associated with a T_QUESTION or T_COLON flagged
     * as a TERNARY_OPERATOR
     */
    public const OTHER_TERNARY_OPERATOR = 4;

    /**
     * The last token of the string opened by the token
     */
    public const STRING_CLOSED_BY = 5;

    /**
     * The first T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR in a chain
     * thereof
     */
    public const CHAIN_OPENED_BY = 6;

    /**
     * A collection of tokens that form a NAMED_DECLARATION
     */
    public const NAMED_DECLARATION_PARTS = 7;

    /**
     * The type of a NAMED_DECLARATION
     */
    public const NAMED_DECLARATION_TYPE = 8;

    /**
     * A collection of property hooks for a NAMED_DECLARATION with type PROPERTY
     * or PROMOTED_PARAM
     */
    public const PROPERTY_HOOKS = 9;

    /**
     * A list of closures that align other tokens with the token when its output
     * column changes
     */
    public const ALIGNMENT_CALLBACKS = 10;

    /**
     * The type applied to an open bracket by the HangingIndentation rule
     */
    public const HANGING_INDENT_PARENT_TYPE = 11;
}
