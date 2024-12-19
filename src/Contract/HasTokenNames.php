<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

/**
 * @internal
 */
interface HasTokenNames
{
    /**
     * @var array<int,string>
     */
    public const TOKEN_NAME = [
        // PHP 8.0
        \T_ATTRIBUTE => 'T_ATTRIBUTE',
        \T_MATCH => 'T_MATCH',
        \T_NAME_FULLY_QUALIFIED => 'T_NAME_FULLY_QUALIFIED',
        \T_NAME_QUALIFIED => 'T_NAME_QUALIFIED',
        \T_NAME_RELATIVE => 'T_NAME_RELATIVE',
        \T_NULLSAFE_OBJECT_OPERATOR => 'T_NULLSAFE_OBJECT_OPERATOR',
        // PHP 8.1
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG',
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',
        \T_ENUM => 'T_ENUM',
        \T_READONLY => 'T_READONLY',
        // PHP 8.4
        \T_PRIVATE_SET => 'T_PRIVATE_SET',
        \T_PROTECTED_SET => 'T_PROTECTED_SET',
        \T_PUBLIC_SET => 'T_PUBLIC_SET',
        \T_PROPERTY_C => 'T_PROPERTY_C',
        // Custom
        \T_ATTRIBUTE_COMMENT => 'T_ATTRIBUTE_COMMENT',
        \T_END_ALT_SYNTAX => 'T_END_ALT_SYNTAX',
        \T_NULL => 'T_NULL',
        \T_OPEN_UNENCLOSED => 'T_OPEN_UNENCLOSED',
        \T_CLOSE_UNENCLOSED => 'T_CLOSE_UNENCLOSED',
        // Single-character
        \T_LOGICAL_NOT => 'T_LOGICAL_NOT',
        \T_DOUBLE_QUOTE => 'T_DOUBLE_QUOTE',
        \T_DOLLAR => 'T_DOLLAR',
        \T_MOD => 'T_MOD',
        \T_AND => 'T_AND',
        \T_OPEN_PARENTHESIS => 'T_OPEN_PARENTHESIS',
        \T_CLOSE_PARENTHESIS => 'T_CLOSE_PARENTHESIS',
        \T_MUL => 'T_MUL',
        \T_PLUS => 'T_PLUS',
        \T_COMMA => 'T_COMMA',
        \T_MINUS => 'T_MINUS',
        \T_CONCAT => 'T_CONCAT',
        \T_DIV => 'T_DIV',
        \T_COLON => 'T_COLON',
        \T_SEMICOLON => 'T_SEMICOLON',
        \T_SMALLER => 'T_SMALLER',
        \T_EQUAL => 'T_EQUAL',
        \T_GREATER => 'T_GREATER',
        \T_QUESTION => 'T_QUESTION',
        \T_AT => 'T_AT',
        \T_OPEN_BRACKET => 'T_OPEN_BRACKET',
        \T_CLOSE_BRACKET => 'T_CLOSE_BRACKET',
        \T_XOR => 'T_XOR',
        \T_BACKTICK => 'T_BACKTICK',
        \T_OPEN_BRACE => 'T_OPEN_BRACE',
        \T_OR => 'T_OR',
        \T_CLOSE_BRACE => 'T_CLOSE_BRACE',
        \T_NOT => 'T_NOT',
    ];
}
