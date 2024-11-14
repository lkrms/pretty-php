<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule\Preset\Internal;

use Lkrms\PrettyPHP\Rule\Preset\Internal\WordPressTokenTypeIndex;
use Lkrms\PrettyPHP\Tests\TokenTypeIndexTest;
use Lkrms\PrettyPHP\TokenTypeIndex;

final class WordPressTokenTypeIndexTest extends TokenTypeIndexTest
{
    protected const ALWAYS_ALLOWED_AT_START_OR_END = [
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_CLOSE_BRACE,
        \T_COLON,
        \T_CONCAT,
        \T_DOUBLE_ARROW,
    ];

    protected const ALWAYS_ALLOWED_AT_START = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AND,
        \T_BOOLEAN_AND,
        \T_BOOLEAN_OR,
        \T_CLOSE_BRACKET,
        \T_CLOSE_PARENTHESIS,
        \T_CLOSE_TAG,
        \T_COALESCE,
        \T_DIV,
        \T_GREATER,
        \T_IS_EQUAL,
        \T_IS_GREATER_OR_EQUAL,
        \T_IS_IDENTICAL,
        \T_IS_NOT_EQUAL,
        \T_IS_NOT_IDENTICAL,
        \T_IS_SMALLER_OR_EQUAL,
        \T_LOGICAL_AND,
        \T_LOGICAL_NOT,
        \T_LOGICAL_OR,
        \T_LOGICAL_XOR,
        \T_MINUS,
        \T_MOD,
        \T_MUL,
        \T_NOT,
        \T_NULLSAFE_OBJECT_OPERATOR,
        \T_OBJECT_OPERATOR,
        \T_OR,
        \T_PLUS,
        \T_POW,
        \T_QUESTION,
        \T_SL,
        \T_SMALLER,
        \T_SPACESHIP,
        \T_SR,
        \T_XOR,
    ];

    protected const ALWAYS_ALLOWED_AT_END = [
        \T_AND_EQUAL,
        \T_COALESCE_EQUAL,
        \T_COMMA,
        \T_COMMENT,
        \T_CONCAT_EQUAL,
        \T_DIV_EQUAL,
        \T_DOC_COMMENT,
        \T_EQUAL,
        \T_EXTENDS,
        \T_IMPLEMENTS,
        \T_MINUS_EQUAL,
        \T_MOD_EQUAL,
        \T_MUL_EQUAL,
        \T_OPEN_BRACE,
        \T_OPEN_BRACKET,
        \T_OPEN_PARENTHESIS,
        \T_OPEN_TAG,
        \T_OPEN_TAG_WITH_ECHO,
        \T_OR_EQUAL,
        \T_PLUS_EQUAL,
        \T_POW_EQUAL,
        \T_SEMICOLON,
        \T_SL_EQUAL,
        \T_SR_EQUAL,
        \T_XOR_EQUAL,
    ];

    protected const MAYBE_ALLOWED_AT_START = self::ALWAYS_ALLOWED_AT_START_OR_END;
    protected const LEADING_OPERATORS = [];
    protected const TRAILING_OPERATORS = [];

    protected const NOT_MOVABLE = [
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_CLOSE_BRACE,
        \T_DOUBLE_ARROW,
    ];

    protected static function getIndex(): TokenTypeIndex
    {
        return new WordPressTokenTypeIndex();
    }
}
