<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Lkrms\Concept\ConvertibleEnumeration;

/**
 * Custom tokens, including polyfills
 *
 * Token constants are defined in `bootstrap.php`.
 *
 * @api
 *
 * @extends ConvertibleEnumeration<int>
 */
final class CustomToken extends ConvertibleEnumeration
{
    public const T_NULL = T_NULL;

    public const T_END_ALT_SYNTAX = T_END_ALT_SYNTAX;

    public const T_ATTRIBUTE_COMMENT = T_ATTRIBUTE_COMMENT;

    /**
     * '!'
     */
    public const T_LOGICAL_NOT = T_LOGICAL_NOT;

    /**
     * '"'
     */
    public const T_DOUBLE_QUOTE = T_DOUBLE_QUOTE;

    /**
     * '$'
     */
    public const T_DOLLAR = T_DOLLAR;

    /**
     * '%'
     */
    public const T_MOD = T_MOD;

    /**
     * '&'
     */
    public const T_AND = T_AND;

    /**
     * '('
     */
    public const T_OPEN_PARENTHESIS = T_OPEN_PARENTHESIS;

    /**
     * ')'
     */
    public const T_CLOSE_PARENTHESIS = T_CLOSE_PARENTHESIS;

    /**
     * '*'
     */
    public const T_MUL = T_MUL;

    /**
     * '+'
     */
    public const T_PLUS = T_PLUS;

    /**
     * ','
     */
    public const T_COMMA = T_COMMA;

    /**
     * '-'
     */
    public const T_MINUS = T_MINUS;

    /**
     * '.'
     */
    public const T_CONCAT = T_CONCAT;

    /**
     * '/'
     */
    public const T_DIV = T_DIV;

    /**
     * ':'
     */
    public const T_COLON = T_COLON;

    /**
     * ';'
     */
    public const T_SEMICOLON = T_SEMICOLON;

    /**
     * '<'
     */
    public const T_SMALLER = T_SMALLER;

    /**
     * '='
     */
    public const T_EQUAL = T_EQUAL;

    /**
     * '>'
     */
    public const T_GREATER = T_GREATER;

    /**
     * '?'
     */
    public const T_QUESTION = T_QUESTION;

    /**
     * '@'
     */
    public const T_AMPERSAND = T_AMPERSAND;

    /**
     * '['
     */
    public const T_OPEN_BRACKET = T_OPEN_BRACKET;

    /**
     * ']'
     */
    public const T_CLOSE_BRACKET = T_CLOSE_BRACKET;

    /**
     * '^'
     */
    public const T_XOR = T_XOR;

    /**
     * '`'
     */
    public const T_BACKTICK = T_BACKTICK;

    /**
     * '{'
     */
    public const T_OPEN_BRACE = T_OPEN_BRACE;

    /**
     * '|'
     */
    public const T_OR = T_OR;

    /**
     * '}'
     */
    public const T_CLOSE_BRACE = T_CLOSE_BRACE;

    /**
     * '~'
     */
    public const T_NOT = T_NOT;

    public const T_BAD_CHARACTER = T_BAD_CHARACTER;

    public const T_ATTRIBUTE = T_ATTRIBUTE;

    public const T_MATCH = T_MATCH;

    public const T_NAME_FULLY_QUALIFIED = T_NAME_FULLY_QUALIFIED;

    public const T_NAME_QUALIFIED = T_NAME_QUALIFIED;

    public const T_NAME_RELATIVE = T_NAME_RELATIVE;

    public const T_NULLSAFE_OBJECT_OPERATOR = T_NULLSAFE_OBJECT_OPERATOR;

    public const T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG = T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG;

    public const T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG = T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;

    public const T_ENUM = T_ENUM;

    public const T_READONLY = T_READONLY;

    protected static $NameMap = [
        T_NULL => 'T_NULL',
        T_END_ALT_SYNTAX => 'T_END_ALT_SYNTAX',
        T_ATTRIBUTE_COMMENT => 'T_ATTRIBUTE_COMMENT',
        T_LOGICAL_NOT => 'T_LOGICAL_NOT',
        T_DOUBLE_QUOTE => 'T_DOUBLE_QUOTE',
        T_DOLLAR => 'T_DOLLAR',
        T_MOD => 'T_MOD',
        T_AND => 'T_AND',
        T_OPEN_PARENTHESIS => 'T_OPEN_PARENTHESIS',
        T_CLOSE_PARENTHESIS => 'T_CLOSE_PARENTHESIS',
        T_MUL => 'T_MUL',
        T_PLUS => 'T_PLUS',
        T_COMMA => 'T_COMMA',
        T_MINUS => 'T_MINUS',
        T_CONCAT => 'T_CONCAT',
        T_DIV => 'T_DIV',
        T_COLON => 'T_COLON',
        T_SEMICOLON => 'T_SEMICOLON',
        T_SMALLER => 'T_SMALLER',
        T_EQUAL => 'T_EQUAL',
        T_GREATER => 'T_GREATER',
        T_QUESTION => 'T_QUESTION',
        T_AMPERSAND => 'T_AMPERSAND',
        T_OPEN_BRACKET => 'T_OPEN_BRACKET',
        T_CLOSE_BRACKET => 'T_CLOSE_BRACKET',
        T_XOR => 'T_XOR',
        T_BACKTICK => 'T_BACKTICK',
        T_OPEN_BRACE => 'T_OPEN_BRACE',
        T_OR => 'T_OR',
        T_CLOSE_BRACE => 'T_CLOSE_BRACE',
        T_NOT => 'T_NOT',
        T_BAD_CHARACTER => 'T_BAD_CHARACTER',
        T_ATTRIBUTE => 'T_ATTRIBUTE',
        T_MATCH => 'T_MATCH',
        T_NAME_FULLY_QUALIFIED => 'T_NAME_FULLY_QUALIFIED',
        T_NAME_QUALIFIED => 'T_NAME_QUALIFIED',
        T_NAME_RELATIVE => 'T_NAME_RELATIVE',
        T_NULLSAFE_OBJECT_OPERATOR => 'T_NULLSAFE_OBJECT_OPERATOR',
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG',
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',
        T_ENUM => 'T_ENUM',
        T_READONLY => 'T_READONLY',
    ];

    protected static $ValueMap = [
        'T_NULL' => T_NULL,
        'T_END_ALT_SYNTAX' => T_END_ALT_SYNTAX,
        'T_ATTRIBUTE_COMMENT' => T_ATTRIBUTE_COMMENT,
        'T_LOGICAL_NOT' => T_LOGICAL_NOT,
        'T_DOUBLE_QUOTE' => T_DOUBLE_QUOTE,
        'T_DOLLAR' => T_DOLLAR,
        'T_MOD' => T_MOD,
        'T_AND' => T_AND,
        'T_OPEN_PARENTHESIS' => T_OPEN_PARENTHESIS,
        'T_CLOSE_PARENTHESIS' => T_CLOSE_PARENTHESIS,
        'T_MUL' => T_MUL,
        'T_PLUS' => T_PLUS,
        'T_COMMA' => T_COMMA,
        'T_MINUS' => T_MINUS,
        'T_CONCAT' => T_CONCAT,
        'T_DIV' => T_DIV,
        'T_COLON' => T_COLON,
        'T_SEMICOLON' => T_SEMICOLON,
        'T_SMALLER' => T_SMALLER,
        'T_EQUAL' => T_EQUAL,
        'T_GREATER' => T_GREATER,
        'T_QUESTION' => T_QUESTION,
        'T_AMPERSAND' => T_AMPERSAND,
        'T_OPEN_BRACKET' => T_OPEN_BRACKET,
        'T_CLOSE_BRACKET' => T_CLOSE_BRACKET,
        'T_XOR' => T_XOR,
        'T_BACKTICK' => T_BACKTICK,
        'T_OPEN_BRACE' => T_OPEN_BRACE,
        'T_OR' => T_OR,
        'T_CLOSE_BRACE' => T_CLOSE_BRACE,
        'T_NOT' => T_NOT,
        'T_BAD_CHARACTER' => T_BAD_CHARACTER,
        'T_ATTRIBUTE' => T_ATTRIBUTE,
        'T_MATCH' => T_MATCH,
        'T_NAME_FULLY_QUALIFIED' => T_NAME_FULLY_QUALIFIED,
        'T_NAME_QUALIFIED' => T_NAME_QUALIFIED,
        'T_NAME_RELATIVE' => T_NAME_RELATIVE,
        'T_NULLSAFE_OBJECT_OPERATOR' => T_NULLSAFE_OBJECT_OPERATOR,
        'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' => T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        'T_ENUM' => T_ENUM,
        'T_READONLY' => T_READONLY,
    ];
}
