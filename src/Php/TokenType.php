<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

class TokenType
{
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

    public const WHITESPACE = [
        T_WHITESPACE,
        T_BAD_CHARACTER,
    ];

    public const COMMENT = [
        T_COMMENT,
        T_DOC_COMMENT,
    ];

    public const OPERATOR_ARITHMETIC = [
        "+",     // Can be unary or binary
        "-",     // Can be unary or binary
        "*",
        "/",
        "%",
        T_POW,     // **
    ];

    public const OPERATOR_ASSIGNMENT = [
        "=",
        T_PLUS_EQUAL,     // +=
        T_MINUS_EQUAL,     // -=
        T_MUL_EQUAL,     // *=
        T_DIV_EQUAL,     // /=
        T_MOD_EQUAL,     // %=
        T_POW_EQUAL,     // **=
        T_AND_EQUAL,     // &=
        T_OR_EQUAL,     // |=
        T_XOR_EQUAL,     // ^=
        T_SL_EQUAL,     // <<=
        T_SR_EQUAL,     // >>=
        T_CONCAT_EQUAL,     // .=
        T_COALESCE_EQUAL,     // ??=
    ];

    public const OPERATOR_BITWISE = [
        "&",
        "|",
        "^",
        "~",
        T_SL,     // <<
        T_SR,     // >>
    ];

    public const OPERATOR_COMPARISON = [
        T_IS_EQUAL,     // ==
        T_IS_IDENTICAL,     // ===
        T_IS_NOT_EQUAL,     // != or <>
        T_IS_NOT_IDENTICAL,     // !==
        "<",
        ">",
        T_IS_SMALLER_OR_EQUAL,     // <=
        T_IS_GREATER_OR_EQUAL,     // >=
        T_SPACESHIP,     // <=>
        T_COALESCE,     // ??
    ];

    public const OPERATOR_TERNARY = [
        "?",
        ":",
    ];

    public const OPERATOR_ERROR_CONTROL = [
        "@",
    ];

    public const OPERATOR_EXECUTION = [
        "`",
    ];

    public const OPERATOR_INCREMENT_DECREMENT = [
        T_INC,     // ++
        T_DEC,     // --
    ];

    public const OPERATOR_LOGICAL = [
        T_LOGICAL_AND,     // and
        T_LOGICAL_OR,     // or
        T_LOGICAL_XOR,     // xor
        "!",
        T_BOOLEAN_AND,     // &&
        T_BOOLEAN_OR,     // ||
    ];

    public const OPERATOR_STRING = [
        ".",
    ];

    public const OPERATOR_INSTANCEOF = [
        T_INSTANCEOF,     // instanceof
    ];

    public const CAST = [
        T_INT_CAST,     // (int) or (integer)
        T_BOOL_CAST,     // (bool) or (boolean)
        T_DOUBLE_CAST,     // (float) or (double) or (real)
        T_STRING_CAST,     // (string)
        T_ARRAY_CAST,     // (array)
        T_OBJECT_CAST,     // (object)
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
        T_STATIC,
        T_TRAIT,
        T_USE,
        T_VAR,
    ];

}
