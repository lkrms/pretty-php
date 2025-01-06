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
     * The content of a normalised DocBlock token (T_DOC_COMMENT or T_COMMENT)
     * after delimiters and trailing whitespace are removed
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
     * The other T_QUESTION or T_COLON associated with a TERNARY
     */
    public const OTHER_TERNARY = 5;

    /**
     * The last token of the string opened by the token
     */
    public const END_STRING = 6;

    /**
     * The first object operator in a chain of method calls
     */
    public const CHAIN = 7;

    /**
     * A collection of tokens that form a DECLARATION
     */
    public const DECLARATION_PARTS = 8;

    /**
     * The type of a DECLARATION
     */
    public const DECLARATION_TYPE = 9;

    /**
     * A collection of property hooks associated with a DECLARATION of type
     * PROPERTY or PROMOTED_PARAM
     */
    public const PROPERTY_HOOKS = 10;

    /**
     * An array of closures that align other tokens with the token when its
     * output column changes
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
