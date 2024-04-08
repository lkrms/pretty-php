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

    /**
     * All operator tokens
     *
     * {@see TokenType::OPERATOR_EXECUTION} is excluded because for formatting
     * purposes, commands between backticks are equivalent to double-quoted
     * strings.
     */
    public const OPERATOR_ALL = [
        ...TokenType::OPERATOR_ARITHMETIC,
        ...TokenType::OPERATOR_ASSIGNMENT,
        ...TokenType::OPERATOR_BITWISE,
        ...TokenType::OPERATOR_COMPARISON,
        ...TokenType::OPERATOR_TERNARY,
        ...TokenType::OPERATOR_ERROR_CONTROL,
        ...TokenType::OPERATOR_INCREMENT_DECREMENT,
        ...TokenType::OPERATOR_LOGICAL,
        ...TokenType::OPERATOR_STRING,
        ...TokenType::OPERATOR_DOUBLE_ARROW,
        ...TokenType::OPERATOR_INSTANCEOF,
    ];

    public const OPERATOR_ARITHMETIC = [
        \T_PLUS,   // Can be unary or binary
        \T_MINUS,  // Can be unary or binary
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
        \T_COALESCE_EQUAL,  // ??=
        ...self::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL_AND_COALESCE,
    ];

    public const OPERATOR_ASSIGNMENT_EXCEPT_COALESCE = [
        \T_EQUAL,  // =
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
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AND,
        \T_OR,
        \T_XOR,
        ...self::OPERATOR_LOGICAL_EXCEPT_NOT,
    ];

    public const OPERATOR_BITWISE = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AND,  // &
        \T_OR,   // |
        \T_XOR,  // ^
        \T_NOT,  // ~
        \T_SL,   // <<
        \T_SR,   // >>
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

    public const OPERATOR_EXECUTION = [
        \T_BACKTICK,  // `
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
        \T_OPEN_PARENTHESIS,
        \T_CLOSE_PARENTHESIS,
        ...self::DECLARATION_TYPE,
        ...self::TYPE_DELIMITER,
    ];

    public const CHAIN_PART = [
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
        \T_STATIC,
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

    public const DECLARATION_CONDENSE = [
        \T_USE,
    ];

    public const DECLARATION_CONDENSE_ONE_LINE = [
        \T_DECLARE,
    ];

    public const DECLARATION_LIST = [
        \T_COMMA,  // ,
        ...self::DECLARATION_TYPE,
    ];

    public const DECLARATION_TYPE = [
        \T_NAMESPACE,
        \T_NS_SEPARATOR,
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
        \T_GLOBAL,
        \T_INTERFACE,
        \T_READONLY,
        \T_TRAIT,
        \T_VAR,
        ...self::VISIBILITY,
    ];

    public const SEMI_RESERVED = [
        ...self::RESERVED_NON_MODIFIER,
        ...self::KEYWORD_MODIFIER,
    ];

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
        \T_TRAIT_C,
    ];

    public const NAME_WITH_READONLY = [
        \T_READONLY,
        ...self::NAME,
    ];

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

    /**
     * Convert a list of token types to an index
     *
     * @return array<int,bool>
     */
    public static function getIndex(int ...$types): array
    {
        return array_fill_keys($types, true) + [
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
            \T_PROTECTED => false,
            \T_PUBLIC => false,
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
    }

    /**
     * Get the names of the token types in an index
     *
     * @param array<int,bool> $index
     * @return string[]
     */
    public static function getIndexNames(array $index): array
    {
        return self::getNames(...self::reduceIndex($index));
    }

    /**
     * Reduce an index to the token types it contains
     *
     * @param array<int,bool> $index
     * @return int[]
     */
    public static function reduceIndex(array $index): array
    {
        foreach ($index as $type => $value) {
            if ($value) {
                $types[] = $type;
            }
        }
        return $types ?? [];
    }

    /**
     * Invert an index
     *
     * @param array<int,bool> $index
     * @return array<int,bool>
     */
    public static function invertIndex(array $index): array
    {
        $index += self::getIndex();
        foreach ($index as &$value) {
            $value = !$value;
        }
        return $index;
    }

    /**
     * Merge one or more token type indexes
     *
     * @param array<int,bool> $index
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    public static function mergeIndexes(array $index, array ...$indexes): array
    {
        $index += self::getIndex();
        foreach ($index as $type => $value) {
            if ($value) {
                continue;
            }
            foreach ($indexes as $_index) {
                if ($_index[$type] ?? false) {
                    $index[$type] = true;
                    break;
                }
            }
        }
        return $index;
    }

    /**
     * Get a token type index containing every entry in the first index
     * that is not present in any of the others
     *
     * @param array<int,bool> $index
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    public static function diffIndexes(array $index, array ...$indexes): array
    {
        $index += self::getIndex();
        foreach ($index as $type => $value) {
            if (!$value) {
                continue;
            }
            foreach ($indexes as $_index) {
                if ($_index[$type] ?? false) {
                    $index[$type] = false;
                    break;
                }
            }
        }
        return $index;
    }

    /**
     * Get a token type index containing every entry in the first index
     * that is present in all of the others
     *
     * @param array<int,bool> $index
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    public static function intersectIndexes(array $index, array ...$indexes): array
    {
        $index += self::getIndex();
        foreach ($index as $type => $value) {
            if (!$value) {
                continue;
            }
            foreach ($indexes as $_index) {
                if (!($_index[$type] ?? false)) {
                    $index[$type] = false;
                    break;
                }
            }
        }
        return $index;
    }

    /**
     * Get a list of token type names from a list of token types
     *
     * @return string[]
     */
    public static function getNames(int ...$types): array
    {
        foreach ($types as $type) {
            $name = token_name($type);
            if (substr($name, 0, 2) !== 'T_') {
                $name = CustomToken::toName($type);
            }
            $names[] = $name;
        }
        return $names ?? [];
    }
}
