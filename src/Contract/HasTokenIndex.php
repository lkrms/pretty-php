<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

/**
 * @internal
 */
interface HasTokenIndex
{
    /**
     * @var array<int,false>
     */
    public const TOKEN_INDEX = [
        \T_ABSTRACT => false,
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => false,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => false,
        \T_AND => false,
        \T_AND_EQUAL => false,
        \T_ARRAY => false,
        \T_ARRAY_CAST => false,
        \T_AS => false,
        \T_AT => false,
        \T_ATTRIBUTE => false,
        \T_ATTRIBUTE_COMMENT => false,
        \T_BACKTICK => false,
        \T_BAD_CHARACTER => false,
        \T_BOOL_CAST => false,
        \T_BOOLEAN_AND => false,
        \T_BOOLEAN_OR => false,
        \T_BREAK => false,
        \T_CALLABLE => false,
        \T_CASE => false,
        \T_CATCH => false,
        \T_CLASS => false,
        \T_CLASS_C => false,
        \T_CLONE => false,
        \T_CLOSE_BRACE => false,
        \T_CLOSE_BRACKET => false,
        \T_CLOSE_PARENTHESIS => false,
        \T_CLOSE_TAG => false,
        \T_COALESCE => false,
        \T_COALESCE_EQUAL => false,
        \T_COLON => false,
        \T_COMMA => false,
        \T_COMMENT => false,
        \T_CONCAT => false,
        \T_CONCAT_EQUAL => false,
        \T_CONST => false,
        \T_CONSTANT_ENCAPSED_STRING => false,
        \T_CONTINUE => false,
        \T_CURLY_OPEN => false,
        \T_DEC => false,
        \T_DECLARE => false,
        \T_DEFAULT => false,
        \T_DIR => false,
        \T_DIV => false,
        \T_DIV_EQUAL => false,
        \T_DNUMBER => false,
        \T_DO => false,
        \T_DOC_COMMENT => false,
        \T_DOLLAR => false,
        \T_DOLLAR_OPEN_CURLY_BRACES => false,
        \T_DOUBLE_ARROW => false,
        \T_DOUBLE_CAST => false,
        \T_DOUBLE_COLON => false,
        \T_DOUBLE_QUOTE => false,
        \T_ECHO => false,
        \T_ELLIPSIS => false,
        \T_ELSE => false,
        \T_ELSEIF => false,
        \T_EMPTY => false,
        \T_ENCAPSED_AND_WHITESPACE => false,
        \T_END_ALT_SYNTAX => false,
        \T_END_HEREDOC => false,
        \T_ENDDECLARE => false,
        \T_ENDFOR => false,
        \T_ENDFOREACH => false,
        \T_ENDIF => false,
        \T_ENDSWITCH => false,
        \T_ENDWHILE => false,
        \T_ENUM => false,
        \T_EQUAL => false,
        \T_EVAL => false,
        \T_EXIT => false,
        \T_EXTENDS => false,
        \T_FILE => false,
        \T_FINAL => false,
        \T_FINALLY => false,
        \T_FN => false,
        \T_FOR => false,
        \T_FOREACH => false,
        \T_FUNC_C => false,
        \T_FUNCTION => false,
        \T_GLOBAL => false,
        \T_GOTO => false,
        \T_GREATER => false,
        \T_HALT_COMPILER => false,
        \T_IF => false,
        \T_IMPLEMENTS => false,
        \T_INC => false,
        \T_INCLUDE => false,
        \T_INCLUDE_ONCE => false,
        \T_INLINE_HTML => false,
        \T_INSTANCEOF => false,
        \T_INSTEADOF => false,
        \T_INT_CAST => false,
        \T_INTERFACE => false,
        \T_IS_EQUAL => false,
        \T_IS_GREATER_OR_EQUAL => false,
        \T_IS_IDENTICAL => false,
        \T_IS_NOT_EQUAL => false,
        \T_IS_NOT_IDENTICAL => false,
        \T_IS_SMALLER_OR_EQUAL => false,
        \T_ISSET => false,
        \T_LINE => false,
        \T_LIST => false,
        \T_LNUMBER => false,
        \T_LOGICAL_AND => false,
        \T_LOGICAL_NOT => false,
        \T_LOGICAL_OR => false,
        \T_LOGICAL_XOR => false,
        \T_MATCH => false,
        \T_METHOD_C => false,
        \T_MINUS => false,
        \T_MINUS_EQUAL => false,
        \T_MOD => false,
        \T_MOD_EQUAL => false,
        \T_MUL => false,
        \T_MUL_EQUAL => false,
        \T_NAME_FULLY_QUALIFIED => false,
        \T_NAME_QUALIFIED => false,
        \T_NAME_RELATIVE => false,
        \T_NAMESPACE => false,
        \T_NEW => false,
        \T_NOT => false,
        \T_NS_C => false,
        \T_NS_SEPARATOR => false,
        \T_NULL => false,
        \T_NULLSAFE_OBJECT_OPERATOR => false,
        \T_NUM_STRING => false,
        \T_OBJECT_CAST => false,
        \T_OBJECT_OPERATOR => false,
        \T_OPEN_BRACE => false,
        \T_OPEN_BRACKET => false,
        \T_OPEN_PARENTHESIS => false,
        \T_OPEN_TAG => false,
        \T_OPEN_TAG_WITH_ECHO => false,
        \T_OR => false,
        \T_OR_EQUAL => false,
        \T_PLUS => false,
        \T_PLUS_EQUAL => false,
        \T_POW => false,
        \T_POW_EQUAL => false,
        \T_PRINT => false,
        \T_PRIVATE => false,
        \T_PRIVATE_SET => false,
        \T_PROPERTY_C => false,
        \T_PROTECTED => false,
        \T_PROTECTED_SET => false,
        \T_PUBLIC => false,
        \T_PUBLIC_SET => false,
        \T_QUESTION => false,
        \T_READONLY => false,
        \T_REQUIRE => false,
        \T_REQUIRE_ONCE => false,
        \T_RETURN => false,
        \T_SEMICOLON => false,
        \T_SL => false,
        \T_SL_EQUAL => false,
        \T_SMALLER => false,
        \T_SPACESHIP => false,
        \T_SR => false,
        \T_SR_EQUAL => false,
        \T_START_HEREDOC => false,
        \T_STATIC => false,
        \T_STRING => false,
        \T_STRING_CAST => false,
        \T_STRING_VARNAME => false,
        \T_SWITCH => false,
        \T_THROW => false,
        \T_TRAIT => false,
        \T_TRAIT_C => false,
        \T_TRY => false,
        \T_UNSET => false,
        \T_UNSET_CAST => false,
        \T_USE => false,
        \T_VAR => false,
        \T_VARIABLE => false,
        \T_WHILE => false,
        \T_WHITESPACE => false,
        \T_XOR => false,
        \T_XOR_EQUAL => false,
        \T_YIELD => false,
        \T_YIELD_FROM => false,
    ];

    /**
     * @var array<int,true>
     */
    public const OPEN_BRACKET = [
        \T_OPEN_BRACE => true,
        \T_OPEN_BRACKET => true,
        \T_OPEN_PARENTHESIS => true,
        \T_ATTRIBUTE => true,
        \T_CURLY_OPEN => true,
        \T_DOLLAR_OPEN_CURLY_BRACES => true,
    ];

    /**
     * @var array<int,true>
     */
    public const CLOSE_BRACKET = [
        \T_CLOSE_BRACE => true,
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
    ];

    /**
     * @var array<int,true>
     */
    public const CAST = [
        \T_INT_CAST => true,
        \T_BOOL_CAST => true,
        \T_DOUBLE_CAST => true,
        \T_STRING_CAST => true,
        \T_ARRAY_CAST => true,
        \T_OBJECT_CAST => true,
        \T_UNSET_CAST => true,
    ];

    /**
     * @var array<int,true>
     */
    public const CHAIN_PART = [
        \T_DOLLAR => true,
        \T_OPEN_BRACE => true,
        \T_OPEN_BRACKET => true,
        \T_OPEN_PARENTHESIS => true,
        \T_STRING => true,
        \T_VARIABLE => true,
    ] + self::CHAIN;

    /**
     * @var array<int,true>
     */
    public const CHAIN = [
        \T_OBJECT_OPERATOR => true,
        \T_NULLSAFE_OBJECT_OPERATOR => true,
    ];

    /**
     * @var array<int,true>
     */
    public const COMMENT = [
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
    ];

    /**
     * @var array<int,true>
     */
    public const VALUE_TYPE_START = [
        \T_ARRAY => true,
        \T_CALLABLE => true,
        \T_OPEN_PARENTHESIS => true,
        \T_QUESTION => true,
    ] + self::DECLARATION_TYPE;

    /**
     * @var array<int,true>
     */
    public const DECLARATION_PART = [
        \T_ATTRIBUTE => true,
        \T_ATTRIBUTE_COMMENT => true,
        \T_CASE => true,
        \T_EXTENDS => true,
        \T_FUNCTION => true,
        \T_IMPLEMENTS => true,
        \T_NAMESPACE => true,
        \T_NS_SEPARATOR => true,
        \T_USE => true,
    ] + self::DECLARATION_ONLY + self::DECLARATION_LIST + self::AMPERSAND;

    /**
     * @var array<int,true>
     */
    public const DECLARATION = [
        \T_CASE => true,
        \T_FUNCTION => true,
        \T_NAMESPACE => true,
        \T_STATIC => true,
        \T_USE => true,
    ] + self::DECLARATION_ONLY;

    /**
     * @var array<int,true>
     */
    public const DECLARATION_ONLY = [
        \T_ABSTRACT => true,
        \T_CONST => true,
        \T_DECLARE => true,
        \T_FINAL => true,
        \T_READONLY => true,
        \T_VAR => true,
    ] + self::DECLARATION_CLASS + self::VISIBILITY;

    /**
     * @var array<int,true>
     */
    public const DECLARATION_CLASS = [
        \T_CLASS => true,
        \T_ENUM => true,
        \T_INTERFACE => true,
        \T_TRAIT => true,
    ];

    /**
     * @var array<int,true>
     */
    public const DECLARATION_LIST = [
        \T_COMMA => true,
    ] + self::DECLARATION_TYPE;

    /**
     * @var array<int,true>
     */
    public const DECLARATION_TYPE = [
        \T_STATIC => true,
    ] + self::NAME;

    /**
     * @var array<int,true>
     */
    public const DEREFERENCEABLE_END = [
        \T_CLOSE_BRACE => true,
        \T_STRING_VARNAME => true,
        \T_VARIABLE => true,
    ] + self::DEREFERENCEABLE_SCALAR_END + self::NAME + self::MAGIC_CONSTANT;

    /**
     * @var array<int,true>
     */
    public const DEREFERENCEABLE_SCALAR_END = [
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
        \T_CONSTANT_ENCAPSED_STRING => true,
        \T_DOUBLE_QUOTE => true,
    ];

    /**
     * @var array<int,true>
     */
    public const NAME = [
        \T_NAME_FULLY_QUALIFIED => true,
        \T_NAME_QUALIFIED => true,
        \T_NAME_RELATIVE => true,
        \T_STRING => true,
    ];

    /**
     * @var array<int,true>
     */
    public const MAGIC_CONSTANT = [
        \T_CLASS_C => true,
        \T_DIR => true,
        \T_FILE => true,
        \T_FUNC_C => true,
        \T_LINE => true,
        \T_METHOD_C => true,
        \T_NS_C => true,
        \T_PROPERTY_C => true,
        \T_TRAIT_C => true,
    ];

    /**
     * @var array<int,true>
     */
    public const KEYWORD = [
        \T_ARRAY => true,
        \T_AS => true,
        \T_BREAK => true,
        \T_CALLABLE => true,
        \T_CASE => true,
        \T_CATCH => true,
        \T_CLASS => true,
        \T_CLONE => true,
        \T_CONST => true,
        \T_CONTINUE => true,
        \T_DECLARE => true,
        \T_DEFAULT => true,
        \T_DO => true,
        \T_ECHO => true,
        \T_ELSE => true,
        \T_ELSEIF => true,
        \T_EMPTY => true,
        \T_ENDDECLARE => true,
        \T_ENDFOR => true,
        \T_ENDFOREACH => true,
        \T_ENDIF => true,
        \T_ENDSWITCH => true,
        \T_ENDWHILE => true,
        \T_ENUM => true,
        \T_EVAL => true,
        \T_EXIT => true,
        \T_EXTENDS => true,
        \T_FINALLY => true,
        \T_FN => true,
        \T_FOR => true,
        \T_FOREACH => true,
        \T_FUNCTION => true,
        \T_GLOBAL => true,
        \T_GOTO => true,
        \T_HALT_COMPILER => true,
        \T_IF => true,
        \T_IMPLEMENTS => true,
        \T_INCLUDE => true,
        \T_INCLUDE_ONCE => true,
        \T_INSTANCEOF => true,
        \T_INSTEADOF => true,
        \T_INTERFACE => true,
        \T_ISSET => true,
        \T_LIST => true,
        \T_LOGICAL_AND => true,
        \T_LOGICAL_OR => true,
        \T_LOGICAL_XOR => true,
        \T_MATCH => true,
        \T_NAMESPACE => true,
        \T_NEW => true,
        \T_PRINT => true,
        \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true,
        \T_RETURN => true,
        \T_SWITCH => true,
        \T_THROW => true,
        \T_TRAIT => true,
        \T_TRY => true,
        \T_UNSET => true,
        \T_USE => true,
        \T_WHILE => true,
        \T_YIELD => true,
        \T_YIELD_FROM => true,
    ] + self::MODIFIER;

    /**
     * @var array<int,true>
     */
    public const MODIFIER = [
        \T_ABSTRACT => true,
        \T_FINAL => true,
        \T_READONLY => true,
        \T_STATIC => true,
        \T_VAR => true,
    ] + self::VISIBILITY;

    /**
     * @var array<int,true>
     */
    public const VISIBILITY = [
        \T_PRIVATE => true,
        \T_PRIVATE_SET => true,
        \T_PROTECTED => true,
        \T_PROTECTED_SET => true,
        \T_PUBLIC => true,
        \T_PUBLIC_SET => true,
    ];

    /**
     * @var array<int,false>
     */
    public const NO_MODIFIER = [
        \T_ABSTRACT => false,
        \T_FINAL => false,
        \T_READONLY => false,
        \T_STATIC => false,
        \T_VAR => false,
        \T_PRIVATE => false,
        \T_PRIVATE_SET => false,
        \T_PROTECTED => false,
        \T_PROTECTED_SET => false,
        \T_PUBLIC => false,
        \T_PUBLIC_SET => false,
    ];

    /**
     * @var array<int,true>
     */
    public const OPERATOR_ARITHMETIC = [
        \T_PLUS => true,
        \T_MINUS => true,
        \T_MUL => true,
        \T_DIV => true,
        \T_MOD => true,
        \T_POW => true,
    ];

    /**
     * @var array<int,true>
     */
    public const OPERATOR_ASSIGNMENT = [
        \T_EQUAL => true,
        \T_COALESCE_EQUAL => true,
        \T_PLUS_EQUAL => true,
        \T_MINUS_EQUAL => true,
        \T_MUL_EQUAL => true,
        \T_DIV_EQUAL => true,
        \T_MOD_EQUAL => true,
        \T_POW_EQUAL => true,
        \T_AND_EQUAL => true,
        \T_OR_EQUAL => true,
        \T_XOR_EQUAL => true,
        \T_SL_EQUAL => true,
        \T_SR_EQUAL => true,
        \T_CONCAT_EQUAL => true,
    ];

    /**
     * @var array<int,true>
     */
    public const OPERATOR_BITWISE = [
        \T_OR => true,
        \T_XOR => true,
        \T_NOT => true,
        \T_SL => true,
        \T_SR => true,
    ] + self::AMPERSAND;

    /**
     * @var array<int,true>
     */
    public const OPERATOR_COMPARISON = [
        \T_COALESCE => true,
        \T_SMALLER => true,
        \T_GREATER => true,
        \T_IS_EQUAL => true,
        \T_IS_IDENTICAL => true,
        \T_IS_NOT_EQUAL => true,
        \T_IS_NOT_IDENTICAL => true,
        \T_IS_SMALLER_OR_EQUAL => true,
        \T_IS_GREATER_OR_EQUAL => true,
        \T_SPACESHIP => true,
    ];

    /**
     * @var array<int,true>
     */
    public const OPERATOR_LOGICAL = [
        \T_BOOLEAN_AND => true,
        \T_BOOLEAN_OR => true,
        \T_LOGICAL_AND => true,
        \T_LOGICAL_OR => true,
        \T_LOGICAL_XOR => true,
        \T_LOGICAL_NOT => true,
    ];

    /**
     * @var array<int,true>
     */
    public const OPERATOR_TERNARY = [
        \T_QUESTION => true,
        \T_COLON => true,
    ];

    /**
     * @var array<int,true>
     */
    public const AMPERSAND = [
        \T_AND => true,
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => true,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => true,
    ];

    /**
     * @var array<int,true>
     */
    public const HAS_STATEMENT_WITH_OPTIONAL_BRACES = [
        \T_DO => true,
        \T_ELSE => true,
    ];

    /**
     * @var array<int,true>
     */
    public const HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES = [
        \T_ELSEIF => true,
        \T_FOR => true,
        \T_FOREACH => true,
        \T_IF => true,
        \T_WHILE => true,
    ];

    /**
     * @var array<int,true>
     */
    public const HAS_STATEMENT_WITH_BRACES = [
        \T_FINALLY => true,
        \T_TRY => true,
    ];

    /**
     * @var array<int,true>
     */
    public const HAS_EXPRESSION_AND_STATEMENT_WITH_BRACES = [
        \T_CATCH => true,
        \T_DECLARE => true,
        \T_SWITCH => true,
    ];

    /**
     * @var array<int,true>
     */
    public const HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES = [
        \T_BREAK => true,
        \T_CASE => true,
        \T_CLONE => true,
        \T_CONTINUE => true,
        \T_ECHO => true,
        \T_INCLUDE => true,
        \T_INCLUDE_ONCE => true,
        \T_PRINT => true,
        \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true,
        \T_RETURN => true,
        \T_THROW => true,
        \T_YIELD => true,
        \T_YIELD_FROM => true,
    ];

    /**
     * @var array<int,true>
     */
    public const NOT_TRIMMABLE = [
        \T_ENCAPSED_AND_WHITESPACE => true,
        \T_INLINE_HTML => true,
    ];

    /**
     * @var array<int,true>
     */
    public const LEFT_TRIMMABLE = [
        \T_CLOSE_TAG => true,
        \T_START_HEREDOC => true,
    ];

    /**
     * @var array<int,true>
     */
    public const RIGHT_TRIMMABLE = [
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
        \T_END_HEREDOC => true,
    ];
}
