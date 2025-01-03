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
     * The token ID of the delimiter associated with a LIST_PARENT token
     */
    public const LIST_DELIMITER = 1;

    /**
     * A collection of items associated with a LIST_PARENT token
     */
    public const LIST_ITEMS = 2;

    /**
     * The number of items associated with a LIST_PARENT token
     */
    public const LIST_ITEM_COUNT = 3;

    /**
     * The LIST_PARENT of the first token in a LIST_ITEM
     */
    public const LIST_PARENT = 4;

    /**
     * The T_COLON or T_QUESTION associated with a T_QUESTION or T_COLON flagged
     * as a TERNARY_OPERATOR
     */
    public const OTHER_TERNARY_OPERATOR = 5;

    /**
     * The last token of the string opened by the token
     */
    public const STRING_CLOSED_BY = 6;

    /**
     * The first T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR in a chain
     * thereof
     */
    public const CHAIN_OPENED_BY = 7;

    /**
     * A collection of tokens that form a NAMED_DECLARATION
     */
    public const NAMED_DECLARATION_PARTS = 8;

    /**
     * The type of a NAMED_DECLARATION
     */
    public const NAMED_DECLARATION_TYPE = 9;

    /**
     * A collection of property hooks for a NAMED_DECLARATION with type PROPERTY
     * or PROMOTED_PARAM
     */
    public const PROPERTY_HOOKS = 10;

    /**
     * A list of closures that align other tokens with the token when its output
     * column changes
     */
    public const ALIGNMENT_CALLBACKS = 11;

    /**
     * The control structure a T_OPEN_UNENCLOSED token is associated with
     */
    public const UNENCLOSED_PARENT = 12;

    /**
     * Whether or not the control structure a T_OPEN_UNENCLOSED token is
     * associated with continues after its T_CLOSE_UNENCLOSED counterpart
     */
    public const UNENCLOSED_CONTINUES = 13;

    /**
     * The non-virtual token a virtual token is bound to
     */
    public const BOUND_TO = 14;

    /**
     * The last non-virtual token before a virtual token, or null if there is no
     * such token
     */
    public const PREV_REAL = 15;

    /**
     * The next non-virtual token after a virtual token, or null if there is no
     * such token
     */
    public const NEXT_REAL = 16;
}
