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
     * The non-virtual token a virtual token is bound to
     */
    public const BOUND_TO = 0;

    /**
     * The last non-virtual token before a virtual token, or null if there is no
     * such token
     */
    public const PREV_REAL = 1;

    /**
     * The next non-virtual token after a virtual token, or null if there is no
     * such token
     */
    public const NEXT_REAL = 2;

    /**
     * The control structure a T_OPEN_UNENCLOSED token is associated with
     */
    public const UNENCLOSED_PARENT = 3;

    /**
     * Whether or not the control structure a T_OPEN_UNENCLOSED token is
     * associated with continues after its T_CLOSE_UNENCLOSED counterpart
     */
    public const UNENCLOSED_CONTINUES = 4;

    /**
     * A collection of tokens that form a DECLARATION
     */
    public const DECLARATION_PARTS = 5;

    /**
     * The type of a DECLARATION
     */
    public const DECLARATION_TYPE = 6;

    /**
     * A collection of property hooks associated with a DECLARATION of type
     * PROPERTY or PROMOTED_PARAM
     */
    public const PROPERTY_HOOKS = 7;

    /**
     * The last token of the string opened by the token
     */
    public const END_STRING = 8;

    /**
     * The first object operator in a chain of method calls
     */
    public const CHAIN = 9;

    /**
     * The other T_QUESTION or T_COLON associated with a TERNARY
     */
    public const OTHER_TERNARY = 10;

    /**
     * The token ID of the delimiter associated with a LIST_PARENT token
     */
    public const LIST_DELIMITER = 11;

    /**
     * A collection of items associated with a LIST_PARENT token
     */
    public const LIST_ITEMS = 12;

    /**
     * The number of items associated with a LIST_PARENT token
     */
    public const LIST_ITEM_COUNT = 13;

    /**
     * The LIST_PARENT of the first token in a LIST_ITEM
     */
    public const LIST_PARENT = 14;

    /**
     * The content of a normalised DocBlock token (T_DOC_COMMENT or T_COMMENT)
     * after delimiters and trailing whitespace are removed
     */
    public const COMMENT_CONTENT = 15;

    /**
     * An array of closures that align other tokens with the token when its
     * output column changes
     */
    public const ALIGNMENT_CALLBACKS = 16;
}
