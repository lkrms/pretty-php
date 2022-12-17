<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10001);
defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') || define('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10002);
defined('T_ATTRIBUTE') || define('T_ATTRIBUTE', 10003);
defined('T_ENUM') || define('T_ENUM', 10004);
defined('T_MATCH') || define('T_MATCH', 10005);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', 10006);
defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', 10007);
defined('T_NAME_RELATIVE') || define('T_NAME_RELATIVE', 10008);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', 10009);
defined('T_READONLY') || define('T_READONLY', 10010);

final class TokenType
{
    public const T_VIRTUAL = 20001;

    public const DO_NOT_MODIFY = [
        T_ENCAPSED_AND_WHITESPACE,
        T_INLINE_HTML,
    ];

    public const DO_NOT_MODIFY_RHS = [
        T_CLOSE_TAG,
        T_START_HEREDOC,
    ];

    public const DO_NOT_MODIFY_LHS = [
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_END_HEREDOC,
    ];

    public const PRESERVE_NEWLINE_AFTER = [
        '(', ',', ';', '[', '}',
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        ...self::COMMENT,
        ...self::OPERATOR_ASSIGNMENT,
        ...self::OPERATOR_BITWISE,
        ...self::OPERATOR_COMPARISON_EXCEPT_COALESCE,
        ...self::OPERATOR_DOUBLE_ARROW,
        ...self::OPERATOR_LOGICAL_EXCEPT_NOT,
    ];

    public const PRESERVE_NEWLINE_BEFORE = [
        '!', ')', ']',
        T_CLOSE_TAG,
        T_COALESCE,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
        ...self::OPERATOR_ARITHMETIC,
        ...self::OPERATOR_TERNARY,
        ...self::OPERATOR_STRING,
    ];

    public const WHITESPACE = [
        T_WHITESPACE,
        T_BAD_CHARACTER,
    ];

    public const COMMENT = [
        T_COMMENT,
        T_DOC_COMMENT,
    ];

    public const NOT_CODE = [
        T_INLINE_HTML,
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_CLOSE_TAG,
        ...self::WHITESPACE,
        ...self::COMMENT,
    ];

    public const AMPERSAND = [
        '&',
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    public const OPERATOR_ARITHMETIC = [
        '+',      // Can be unary or binary
        '-',      // Can be unary or binary
        '*',
        '/',
        '%',
        T_POW,    // **
    ];

    public const OPERATOR_ASSIGNMENT = [
        '=',
        T_PLUS_EQUAL,        // +=
        T_MINUS_EQUAL,       // -=
        T_MUL_EQUAL,         // *=
        T_DIV_EQUAL,         // /=
        T_MOD_EQUAL,         // %=
        T_POW_EQUAL,         // **=
        T_AND_EQUAL,         // &=
        T_OR_EQUAL,          // |=
        T_XOR_EQUAL,         // ^=
        T_SL_EQUAL,          // <<=
        T_SR_EQUAL,          // >>=
        T_CONCAT_EQUAL,      // .=
        T_COALESCE_EQUAL,    // ??=
    ];

    public const OPERATOR_BITWISE = [
        '&', '|', '^', '~',
        T_SL,                  // <<
        T_SR,                  // >>
    ];

    public const OPERATOR_COMPARISON_EXCEPT_COALESCE = [
        '<', '>',
        T_IS_EQUAL,               // ==
        T_IS_IDENTICAL,           // ===
        T_IS_NOT_EQUAL,           // != or <>
        T_IS_NOT_IDENTICAL,       // !==
        T_IS_SMALLER_OR_EQUAL,    // <=
        T_IS_GREATER_OR_EQUAL,    // >=
        T_SPACESHIP,              // <=>
    ];

    public const OPERATOR_COMPARISON = [
        T_COALESCE,    // ??
        ...self::OPERATOR_COMPARISON_EXCEPT_COALESCE,
    ];

    public const OPERATOR_TERNARY = [
        '?',
        ':',
    ];

    public const OPERATOR_ERROR_CONTROL = [
        '@',
    ];

    public const OPERATOR_EXECUTION = [
        '`',
    ];

    public const OPERATOR_INCREMENT_DECREMENT = [
        T_INC,    // ++
        T_DEC,    // --
    ];

    public const OPERATOR_LOGICAL_EXCEPT_NOT = [
        T_LOGICAL_AND,    // and
        T_LOGICAL_OR,     // or
        T_LOGICAL_XOR,    // xor
        T_BOOLEAN_AND,    // &&
        T_BOOLEAN_OR,     // ||
    ];

    public const OPERATOR_LOGICAL = [
        '!',
        ...self::OPERATOR_LOGICAL_EXCEPT_NOT,
    ];

    public const OPERATOR_STRING = [
        '.',
    ];

    public const OPERATOR_DOUBLE_ARROW = [
        T_DOUBLE_ARROW,    // =>
    ];

    public const OPERATOR_INSTANCEOF = [
        T_INSTANCEOF,    // instanceof
    ];

    public const CAST = [
        T_INT_CAST,       // (int) or (integer)
        T_BOOL_CAST,      // (bool) or (boolean)
        T_DOUBLE_CAST,    // (float) or (double) or (real)
        T_STRING_CAST,    // (string)
        T_ARRAY_CAST,     // (array)
        T_OBJECT_CAST,    // (object)
        T_UNSET_CAST,     // (unset)
    ];

    public const DECLARATION = [
        T_ABSTRACT,
        T_CLASS,
        T_CONST,
        T_ENUM,
        T_EXTENDS,
        T_FINAL,
        T_FUNCTION,
        T_IMPLEMENTS,
        T_INTERFACE,
        T_NAMESPACE,
        T_PRIVATE,
        T_PROTECTED,
        T_PUBLIC,
        T_READONLY,
        T_TRAIT,
        T_USE,
        T_VAR,
    ];

    public const DECLARATION_PART = [
        T_STATIC,
        T_STRING,
        ...self::DECLARATION,
    ];

    public const DECLARATION_CONDENSE = [
        T_CONST,
        T_USE,
    ];

    public const HAS_STATEMENT_WITH_OPTIONAL_BRACES = [
        T_DO,
        T_ELSE,
    ];

    public const HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES = [
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_WHILE,
    ];

    public const HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES = [
        T_BREAK,
        T_CASE,
        T_CLONE,
        T_CONTINUE,
        T_ECHO,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_PRINT,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_RETURN,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
    ];

    public const ADD_SPACE_AROUND = [
        T_AS,
        T_INSTEADOF,
        T_USE,
    ];

    public const ADD_SPACE_BEFORE = [
        T_ARRAY,
        T_CALLABLE,
        T_ELLIPSIS,
        T_STRING,
        T_VARIABLE,
    ];

    public const ADD_SPACE_AFTER = [
        T_BREAK,
        T_CASE,
        T_CATCH,
        T_CLONE,
        T_CONTINUE,
        T_ECHO,
        T_ELSEIF,
        T_EXIT,
        T_FOR,
        T_FOREACH,
        T_FUNCTION,
        T_GOTO,
        T_IF,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_MATCH,
        T_NEW,
        T_PRINT,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_RETURN,
        T_SWITCH,
        T_THROW,
        T_WHILE,
        T_YIELD,
        T_YIELD_FROM,
        ...self::CAST,
    ];

    public const SUPPRESS_SPACE_BEFORE = [
        T_NS_SEPARATOR,
    ];

    public const SUPPRESS_SPACE_AFTER = [
        T_DOUBLE_COLON,
        T_ELLIPSIS,
        T_NS_SEPARATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    public const CAN_START_ALTERNATIVE_SYNTAX = [
        T_DECLARE,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_SWITCH,
        T_WHILE,
    ];

    public const CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITHOUT_EXPRESSION = [
        T_ELSE,
    ];

    public const CAN_CONTINUE_ALTERNATIVE_SYNTAX_WITH_EXPRESSION = [
        T_ELSEIF,
    ];

    public const ENDS_ALTERNATIVE_SYNTAX = [
        T_ENDDECLARE,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDIF,
        T_ENDSWITCH,
        T_ENDWHILE,
    ];

    public const MAGIC_CONSTANT = [
        T_CLASS_C,
        T_DIR,
        T_FILE,
        T_FUNC_C,
        T_LINE,
        T_METHOD_C,
        T_NS_C,
        T_TRAIT_C,
    ];

    public const KEYWORD = [
        T_ARRAY,
        T_AS,
        T_BREAK,
        T_CALLABLE,
        T_CASE,
        T_CATCH,
        T_CLONE,
        T_CONTINUE,
        T_DECLARE,
        T_DEFAULT,
        T_DO,
        T_ECHO,
        T_ELLIPSIS,
        T_ELSE,
        T_ELSEIF,
        T_EMPTY,
        T_EVAL,
        T_EXIT,
        T_FINALLY,
        T_FN,
        T_FOR,
        T_FOREACH,
        T_GLOBAL,
        T_GOTO,
        T_HALT_COMPILER,
        T_IF,
        T_INCLUDE_ONCE,
        T_INCLUDE,
        T_INSTEADOF,
        T_ISSET,
        T_LIST,
        T_MATCH,
        T_NEW,
        T_PRINT,
        T_REQUIRE_ONCE,
        T_REQUIRE,
        T_RETURN,
        T_STATIC,
        T_SWITCH,
        T_THROW,
        T_TRY,
        T_UNSET,
        T_WHILE,
        T_YIELD_FROM,
        T_YIELD,
        ...self::DECLARATION,
        ...self::ENDS_ALTERNATIVE_SYNTAX,
    ];

    public const OTHER = [
        T_ATTRIBUTE,
        T_CONSTANT_ENCAPSED_STRING,
        T_CURLY_OPEN,
        T_DNUMBER,
        T_DOLLAR_OPEN_CURLY_BRACES,
        T_LNUMBER,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_NAME_RELATIVE,
        T_NS_SEPARATOR,
        T_NUM_STRING,
        T_STRING_VARNAME,
    ];
}
