<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractDictionary;

/**
 * Tokens by type
 *
 * @api
 *
 * @extends AbstractDictionary<int[]>
 */
final class TokenType extends AbstractDictionary
{
    public const NOT_CODE = [
        \T_INLINE_HTML,
        \T_OPEN_TAG,
        \T_OPEN_TAG_WITH_ECHO,
        \T_CLOSE_TAG,
        \T_WHITESPACE,
        ...self::COMMENT,
    ];

    public const COMMENT = [
        \T_COMMENT,
        \T_DOC_COMMENT,
    ];

    public const OPERATOR_ALL = [
        ...self::OPERATOR_ARITHMETIC,
        ...self::OPERATOR_ASSIGNMENT,
        ...self::OPERATOR_BITWISE,
        ...self::OPERATOR_COMPARISON,
        ...self::OPERATOR_TERNARY,
        ...self::OPERATOR_ERROR_CONTROL,
        ...self::OPERATOR_INCREMENT_DECREMENT,
        ...self::OPERATOR_LOGICAL,
        ...self::OPERATOR_STRING,
        ...self::OPERATOR_DOUBLE_ARROW,
        ...self::OPERATOR_INSTANCEOF,
    ];

    public const OPERATOR_ARITHMETIC = [
        \T_PLUS,   // May be unary or binary
        \T_MINUS,  // May be unary or binary
        \T_MUL,    // *
        \T_DIV,    // /
        \T_MOD,    // %
        \T_POW,    // **
    ];

    public const OPERATOR_ASSIGNMENT = [
        \T_EQUAL,           // =
        \T_COALESCE_EQUAL,  // ??=
        ...self::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL_AND_COALESCE,
    ];

    public const OPERATOR_ASSIGNMENT_EXCEPT_EQUAL = [
        \T_COALESCE_EQUAL,
        ...self::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL_AND_COALESCE,
    ];

    public const OPERATOR_ASSIGNMENT_EXCEPT_COALESCE = [
        \T_EQUAL,
        ...self::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL_AND_COALESCE,
    ];

    public const OPERATOR_ASSIGNMENT_EXCEPT_EQUAL_AND_COALESCE = [
        \T_PLUS_EQUAL,    // +=
        \T_MINUS_EQUAL,   // -=
        \T_MUL_EQUAL,     // *=
        \T_DIV_EQUAL,     // /=
        \T_MOD_EQUAL,     // %=
        \T_POW_EQUAL,     // **=
        \T_AND_EQUAL,     // &=
        \T_OR_EQUAL,      // |=
        \T_XOR_EQUAL,     // ^=
        \T_SL_EQUAL,      // <<=
        \T_SR_EQUAL,      // >>=
        \T_CONCAT_EQUAL,  // .=
    ];

    public const OPERATOR_BOOLEAN_EXCEPT_NOT = [
        \T_OR,
        \T_XOR,
        ...self::AMPERSAND,
        ...self::OPERATOR_LOGICAL_EXCEPT_NOT,
    ];

    public const OPERATOR_BITWISE = [
        \T_OR,   // |
        \T_XOR,  // ^
        \T_NOT,  // ~
        \T_SL,   // <<
        \T_SR,   // >>
        ...self::AMPERSAND,
    ];

    public const OPERATOR_COMPARISON = [
        \T_COALESCE,  // ??
        ...self::OPERATOR_COMPARISON_EXCEPT_COALESCE,
    ];

    public const OPERATOR_COMPARISON_EXCEPT_COALESCE = [
        \T_SMALLER,              // <
        \T_GREATER,              // >
        \T_IS_EQUAL,             // ==
        \T_IS_IDENTICAL,         // ===
        \T_IS_NOT_EQUAL,         // != or <>
        \T_IS_NOT_IDENTICAL,     // !==
        \T_IS_SMALLER_OR_EQUAL,  // <=
        \T_IS_GREATER_OR_EQUAL,  // >=
        \T_SPACESHIP,            // <=>
    ];

    public const OPERATOR_TERNARY = [
        \T_QUESTION,  // ?
        \T_COLON,     // :
    ];

    public const OPERATOR_ERROR_CONTROL = [
        \T_AT,  // @
    ];

    public const OPERATOR_INCREMENT_DECREMENT = [
        \T_INC,  // ++
        \T_DEC,  // --
    ];

    public const OPERATOR_LOGICAL = [
        \T_LOGICAL_NOT,  // !
        ...self::OPERATOR_LOGICAL_EXCEPT_NOT,
    ];

    public const OPERATOR_LOGICAL_EXCEPT_NOT = [
        \T_LOGICAL_AND,  // and
        \T_LOGICAL_OR,   // or
        \T_LOGICAL_XOR,  // xor
        \T_BOOLEAN_AND,  // &&
        \T_BOOLEAN_OR,   // ||
    ];

    public const OPERATOR_STRING = [
        \T_CONCAT,  // .
    ];

    public const OPERATOR_DOUBLE_ARROW = [
        \T_DOUBLE_ARROW,  // =>
    ];

    public const OPERATOR_INSTANCEOF = [
        \T_INSTANCEOF,  // instanceof
    ];

    public const CAST = [
        \T_INT_CAST,     // (int) or (integer)
        \T_BOOL_CAST,    // (bool) or (boolean)
        \T_DOUBLE_CAST,  // (float) or (double) or (real)
        \T_STRING_CAST,  // (string)
        \T_ARRAY_CAST,   // (array)
        \T_OBJECT_CAST,  // (object)
        \T_UNSET_CAST,   // (unset)
    ];

    public const VALUE_TYPE = [
        \T_CLOSE_PARENTHESIS,
        ...self::TYPE_DELIMITER,
        ...self::VALUE_TYPE_START,
    ];

    public const VALUE_TYPE_START = [
        \T_ARRAY,
        \T_CALLABLE,
        \T_OPEN_PARENTHESIS,
        \T_QUESTION,
        ...self::DECLARATION_TYPE,
    ];

    public const CHAIN_EXPRESSION = [
        // '"' ... '"'
        \T_CURLY_OPEN,
        \T_DOLLAR_OPEN_CURLY_BRACES,
        \T_DOUBLE_QUOTE,
        \T_ENCAPSED_AND_WHITESPACE,
        // Other dereferenceables
        \T_ARRAY,
        \T_CONSTANT_ENCAPSED_STRING,
        \T_DOUBLE_COLON,
        \T_NAME_FULLY_QUALIFIED,
        \T_NAME_QUALIFIED,
        \T_NAME_RELATIVE,
        \T_STATIC,
        ...self::CHAIN_PART,
        ...self::MAGIC_CONSTANT,
    ];

    public const CHAIN_PART = [
        \T_DOLLAR,
        \T_OPEN_BRACE,
        \T_OPEN_BRACKET,
        \T_OPEN_PARENTHESIS,
        \T_STRING,
        \T_VARIABLE,
        ...self::CHAIN,
    ];

    public const CHAIN = [
        \T_OBJECT_OPERATOR,           // ->
        \T_NULLSAFE_OBJECT_OPERATOR,  // ?->
    ];

    public const HAS_STATEMENT = [
        ...self::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
        ...self::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
        ...self::HAS_STATEMENT_WITH_BRACES,
        ...self::HAS_EXPRESSION_AND_STATEMENT_WITH_BRACES,
    ];

    public const HAS_STATEMENT_WITH_OPTIONAL_BRACES = [
        \T_DO,
        \T_ELSE,
    ];

    public const HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES = [
        \T_ELSEIF,
        \T_FOR,
        \T_FOREACH,
        \T_IF,
        \T_WHILE,
    ];

    public const HAS_STATEMENT_WITH_BRACES = [
        \T_FINALLY,
        \T_TRY,
    ];

    public const HAS_EXPRESSION_AND_STATEMENT_WITH_BRACES = [
        \T_CATCH,
        \T_DECLARE,
        \T_SWITCH,
    ];

    public const HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES = [
        \T_BREAK,
        \T_CASE,
        \T_CLONE,
        \T_CONTINUE,
        \T_ECHO,
        \T_INCLUDE,
        \T_INCLUDE_ONCE,
        \T_PRINT,
        \T_REQUIRE,
        \T_REQUIRE_ONCE,
        \T_RETURN,
        \T_THROW,
        \T_YIELD,
        \T_YIELD_FROM,
    ];

    public const ALT_SYNTAX_START = [
        \T_DECLARE,
        \T_FOR,
        \T_FOREACH,
        \T_IF,
        \T_SWITCH,
        \T_WHILE,
    ];

    public const ALT_SYNTAX_CONTINUE = [
        ...self::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION,
        ...self::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION,
    ];

    public const ALT_SYNTAX_CONTINUE_WITH_EXPRESSION = [
        \T_ELSEIF,
    ];

    public const ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION = [
        \T_ELSE,
    ];

    public const ALT_SYNTAX_END = [
        \T_ENDDECLARE,
        \T_ENDFOR,
        \T_ENDFOREACH,
        \T_ENDIF,
        \T_ENDSWITCH,
        \T_ENDWHILE,
    ];

    public const DECLARATION_PART_WITH_NEW_AND_VALUE_TYPE = [
        \T_ARRAY,
        \T_CALLABLE,
        \T_OPEN_PARENTHESIS,
        \T_CLOSE_PARENTHESIS,
        \T_COLON,
        \T_OR,
        \T_QUESTION,
        ...self::DECLARATION_PART_WITH_NEW,
    ];

    public const DECLARATION_PART_WITH_NEW = [
        \T_NEW,
        ...self::DECLARATION_PART,
    ];

    public const DECLARATION_PART = [
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_CASE,
        \T_EXTENDS,
        \T_FUNCTION,
        \T_IMPLEMENTS,
        \T_NAMESPACE,
        \T_NS_SEPARATOR,
        \T_USE,
        ...self::DECLARATION_EXCEPT_MULTI_PURPOSE,
        ...self::DECLARATION_LIST,
        ...self::AMPERSAND,
    ];

    public const DECLARATION_TOP_LEVEL = [
        \T_FUNCTION,
        \T_NAMESPACE,
        ...self::DECLARATION_CLASS,
    ];

    public const DECLARATION_CLASS = [
        \T_CLASS,
        \T_ENUM,
        \T_INTERFACE,
        \T_TRAIT,
    ];

    public const DECLARATION_EXCEPT_MODIFIERS = [
        \T_CASE,
        \T_CLASS,
        \T_CONST,
        \T_DECLARE,
        \T_ENUM,
        \T_FUNCTION,
        \T_INTERFACE,
        \T_NAMESPACE,
        \T_TRAIT,
        \T_USE,
    ];

    public const DECLARATION_LIST = [
        \T_COMMA,
        ...self::DECLARATION_TYPE,
    ];

    public const DECLARATION_TYPE = [
        \T_STATIC,
        ...self::NAME,
    ];

    public const DECLARATION = [
        \T_CASE,
        \T_FUNCTION,
        \T_NAMESPACE,
        \T_STATIC,
        \T_USE,
        ...self::DECLARATION_EXCEPT_MULTI_PURPOSE,
    ];

    public const DECLARATION_EXCEPT_MULTI_PURPOSE = [
        \T_ABSTRACT,
        \T_CLASS,
        \T_CONST,
        \T_DECLARE,
        \T_ENUM,
        \T_FINAL,
        \T_INTERFACE,
        \T_READONLY,
        \T_TRAIT,
        \T_VAR,
        ...self::VISIBILITY,
    ];

    /**
     * identifier_maybe_reserved
     */
    public const MAYBE_RESERVED = [
        \T_STRING,
        ...self::SEMI_RESERVED,
    ];

    /**
     * semi_reserved
     */
    public const SEMI_RESERVED = [
        ...self::RESERVED_NON_MODIFIER,
        ...self::KEYWORD_MODIFIER,
    ];

    /**
     * reserved_non_modifiers
     */
    public const RESERVED_NON_MODIFIER = [
        ...self::KEYWORD_NON_MODIFIER,
        ...self::MAGIC_CONSTANT,
    ];

    public const KEYWORD = [
        \T_YIELD_FROM,
        ...self::KEYWORD_MODIFIER,
        ...self::KEYWORD_NON_MODIFIER,
    ];

    public const KEYWORD_MODIFIER = [
        \T_ABSTRACT,
        \T_FINAL,
        \T_READONLY,
        \T_STATIC,
        ...self::VISIBILITY,
    ];

    public const KEYWORD_NON_MODIFIER = [
        \T_ARRAY,
        \T_AS,
        \T_BREAK,
        \T_CALLABLE,
        \T_CASE,
        \T_CATCH,
        \T_CLASS,
        \T_CLONE,
        \T_CONST,
        \T_CONTINUE,
        \T_DECLARE,
        \T_DEFAULT,
        \T_DO,
        \T_ECHO,
        \T_ELSE,
        \T_ELSEIF,
        \T_EMPTY,
        \T_ENDDECLARE,
        \T_ENDFOR,
        \T_ENDFOREACH,
        \T_ENDIF,
        \T_ENDSWITCH,
        \T_ENDWHILE,
        \T_ENUM,
        \T_EVAL,
        \T_EXIT,
        \T_EXTENDS,
        \T_FINALLY,
        \T_FN,
        \T_FOR,
        \T_FOREACH,
        \T_FUNCTION,
        \T_GLOBAL,
        \T_GOTO,
        \T_HALT_COMPILER,
        \T_IF,
        \T_IMPLEMENTS,
        \T_INCLUDE,
        \T_INCLUDE_ONCE,
        \T_INSTANCEOF,
        \T_INSTEADOF,
        \T_INTERFACE,
        \T_ISSET,
        \T_LIST,
        \T_LOGICAL_AND,
        \T_LOGICAL_OR,
        \T_LOGICAL_XOR,
        \T_MATCH,
        \T_NAMESPACE,
        \T_NEW,
        \T_PRINT,
        \T_REQUIRE,
        \T_REQUIRE_ONCE,
        \T_RETURN,
        \T_SWITCH,
        \T_THROW,
        \T_TRAIT,
        \T_TRY,
        \T_UNSET,
        \T_USE,
        \T_VAR,
        \T_WHILE,
        \T_YIELD,
    ];

    public const DEREFERENCEABLE_END = [
        \T_CLOSE_BRACE,
        \T_STRING_VARNAME,
        \T_VARIABLE,
        ...self::DEREFERENCEABLE_SCALAR_END,
        ...self::NAME,
        ...self::MAGIC_CONSTANT,
    ];

    public const DEREFERENCEABLE_SCALAR_END = [
        \T_CLOSE_BRACKET,
        \T_CLOSE_PARENTHESIS,
        \T_CONSTANT_ENCAPSED_STRING,
        \T_DOUBLE_QUOTE,
    ];

    public const MAGIC_CONSTANT = [
        \T_CLASS_C,
        \T_DIR,
        \T_FILE,
        \T_FUNC_C,
        \T_LINE,
        \T_METHOD_C,
        \T_NS_C,
        \T_PROPERTY_C,
        \T_TRAIT_C,
    ];

    public const NAME_WITH_READONLY = [
        \T_READONLY,
        ...self::NAME,
    ];

    /**
     * name
     */
    public const NAME = [
        \T_NAME_FULLY_QUALIFIED,
        \T_NAME_QUALIFIED,
        \T_NAME_RELATIVE,
        \T_STRING,
    ];

    public const VISIBILITY_WITH_READONLY = [
        \T_READONLY,
        ...self::VISIBILITY,
    ];

    public const VISIBILITY = [
        \T_PRIVATE,
        \T_PROTECTED,
        \T_PUBLIC,
    ];

    public const MAYBE_ANONYMOUS = [
        \T_CLASS,
        \T_FN,
        \T_FUNCTION,
    ];

    public const TYPE_DELIMITER = [
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_OR,
    ];

    public const AMPERSAND = [
        \T_AND,
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    public const RETURN = [
        \T_RETURN,
        \T_YIELD,
        \T_YIELD_FROM,
    ];
}
