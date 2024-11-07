<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Contract\HasTokenIndex;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;

/**
 * Token type indexes
 *
 * @api
 */
class TokenTypeIndex implements HasTokenIndex, Immutable
{
    use HasMutator;

    protected const FIRST = 0;
    protected const LAST = 1;
    protected const MIXED = 2;

    /**
     * @var array<int,bool>
     */
    private const DEFAULT_ALLOW_NEWLINE_BEFORE = [
        \T_ATTRIBUTE => true,
        \T_ATTRIBUTE_COMMENT => true,
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
        \T_CLOSE_TAG => true,
        \T_COALESCE => true,
        \T_CONCAT => true,
        \T_DOUBLE_ARROW => true,
        \T_LOGICAL_NOT => true,
        \T_OBJECT_OPERATOR => true,
        \T_NULLSAFE_OBJECT_OPERATOR => true,
    ]
        + self::OPERATOR_ARITHMETIC
        + self::OPERATOR_BITWISE
        + self::OPERATOR_TERNARY
        + self::TOKEN_INDEX;

    /**
     * @var array<int,bool>
     */
    private const DEFAULT_ALLOW_NEWLINE_AFTER = [
        \T_ATTRIBUTE => true,
        \T_ATTRIBUTE_COMMENT => true,
        \T_CLOSE_BRACE => true,
        \T_COLON => true,
        \T_COMMA => true,
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
        \T_DOUBLE_ARROW => true,
        \T_EXTENDS => true,
        \T_IMPLEMENTS => true,
        \T_OPEN_BRACE => true,
        \T_OPEN_BRACKET => true,
        \T_OPEN_PARENTHESIS => true,
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
        \T_SEMICOLON => true,
        \T_COALESCE => false,
        \T_LOGICAL_NOT => false,
    ]
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_COMPARISON
        + self::OPERATOR_LOGICAL
        + self::TOKEN_INDEX;

    // Syntax:

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_CLOSE_BRACE,
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_ATTRIBUTE, T_CURLY_OPEN,
     * T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @var array<int,bool>
     */
    public array $Bracket = self::OPEN_BRACKET
        + self::CLOSE_BRACKET
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE,
     * T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @var array<int,bool>
     */
    public array $OpenBracket = self::OPEN_BRACKET
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE,
     * T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_LOGICAL_NOT, T_NOT
     *
     * @var array<int,bool>
     */
    public array $OpenBracketOrNot = self::OPEN_BRACKET + [
        \T_LOGICAL_NOT => true,
        \T_NOT => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS
     *
     * @var array<int,bool>
     */
    public array $CloseBracket = self::CLOSE_BRACKET
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_END_ALT_SYNTAX
     *
     * @var array<int,bool>
     */
    public array $CloseBracketOrEndAlt = self::CLOSE_BRACKET + [
        \T_END_ALT_SYNTAX => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_COMMA
     *
     * @var array<int,bool>
     */
    public array $CloseBracketOrComma = self::CLOSE_BRACKET + [
        \T_COMMA => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE
     *
     * @var array<int,bool>
     */
    public array $OpenBracketExceptBrace = [
        \T_OPEN_BRACE => false,
        \T_CURLY_OPEN => false,
        \T_DOLLAR_OPEN_CURLY_BRACES => false,
    ]
        + self::OPEN_BRACKET
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_BRACE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @var array<int,bool>
     */
    public array $OpenBrace = [
        \T_OPEN_BRACE => true,
        \T_CURLY_OPEN => true,
        \T_DOLLAR_OPEN_CURLY_BRACES => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO
     *
     * @var array<int,bool>
     */
    public array $OpenTag = [
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_DECLARE, T_FOR, T_FOREACH, T_IF, T_SWITCH, T_WHILE
     *
     * @var array<int,bool>
     */
    public array $AltStart = [
        \T_DECLARE => true,
        \T_FOR => true,
        \T_FOREACH => true,
        \T_IF => true,
        \T_SWITCH => true,
        \T_WHILE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ELSE, T_ELSEIF
     *
     * @var array<int,bool>
     */
    public array $AltContinue = [
        \T_ELSEIF => true,
        \T_ELSE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ELSEIF
     *
     * @var array<int,bool>
     */
    public array $AltContinueWithExpression = [
        \T_ELSEIF => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ELSE
     *
     * @var array<int,bool>
     */
    public array $AltContinueWithNoExpression = [
        \T_ELSE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ENDDECLARE, T_ENDFOR, T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE
     *
     * @var array<int,bool>
     */
    public array $AltEnd = [
        \T_ENDDECLARE => true,
        \T_ENDFOR => true,
        \T_ENDFOREACH => true,
        \T_ENDIF => true,
        \T_ENDSWITCH => true,
        \T_ENDWHILE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_AND, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
     *
     * @var array<int,bool>
     */
    public array $Ampersand = self::AMPERSAND
        + self::TOKEN_INDEX;

    /**
     * T_ATTRIBUTE, T_ATTRIBUTE_COMMENT
     *
     * @var array<int,bool>
     */
    public array $Attribute = [
        \T_ATTRIBUTE => true,
        \T_ATTRIBUTE_COMMENT => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * Casts
     *
     * @var array<int,bool>
     */
    public array $Cast = self::CAST
        + self::TOKEN_INDEX;

    /**
     * T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR
     *
     * @var array<int,bool>
     */
    public array $Chain = self::CHAIN
        + self::TOKEN_INDEX;

    /**
     * T_CLASS, T_FUNCTION
     *
     * @var array<int,bool>
     */
    public array $ClassOrFunction = [
        \T_CLASS => true,
        \T_FUNCTION => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_COMMA, T_DOUBLE_ARROW
     *
     * @var array<int,bool>
     */
    public array $CommaOrDoubleArrow = [
        \T_COMMA => true,
        \T_DOUBLE_ARROW => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_COMMENT, T_DOC_COMMENT
     *
     * @var array<int,bool>
     */
    public array $Comment = self::COMMENT
        + self::TOKEN_INDEX;

    /**
     * T_ABSTRACT, T_CASE, T_CLASS, T_CONST, T_DECLARE, T_ENUM, T_FINAL,
     * T_FUNCTION, T_INTERFACE, T_NAMESPACE, T_PRIVATE, T_PROTECTED, T_PUBLIC,
     * T_READONLY, T_STATIC, T_TRAIT, T_USE, T_VAR
     *
     * @var array<int,bool>
     */
    public array $Declaration = self::DECLARATION
        + self::TOKEN_INDEX;

    /**
     * T_CASE, T_CLASS, T_CONST, T_DECLARE, T_ENUM, T_FUNCTION, T_INTERFACE,
     * T_NAMESPACE, T_TRAIT, T_USE
     *
     * @var array<int,bool>
     */
    public array $DeclarationExceptModifiers = self::NO_MODIFIER
        + self::DECLARATION
        + self::TOKEN_INDEX;

    /**
     * T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT
     *
     * @var array<int,bool>
     */
    public array $DeclarationClass = self::DECLARATION_CLASS
        + self::TOKEN_INDEX;

    /**
     * T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_TRAIT
     *
     * @var array<int,bool>
     */
    public array $DeclarationClassOrFunction = self::DECLARATION_CLASS + [
        \T_FUNCTION => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_COMMA, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE,
     * T_STATIC, T_STRING
     *
     * @var array<int,bool>
     */
    public array $DeclarationList = self::DECLARATION_LIST
        + self::TOKEN_INDEX;

    /**
     * Visibility modifiers, T_READONLY, T_STATIC, T_VAR
     *
     * @var array<int,bool>
     */
    public array $DeclarationPropertyOrVariable = [
        \T_READONLY => true,
        \T_STATIC => true,
        \T_VAR => true,
    ]
        + self::VISIBILITY
        + self::TOKEN_INDEX;

    /**
     * T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_NAMESPACE, T_TRAIT
     *
     * @var array<int,bool>
     */
    public array $DeclarationTopLevel = [
        \T_FUNCTION => true,
        \T_NAMESPACE => true,
    ]
        + self::DECLARATION_CLASS
        + self::TOKEN_INDEX;

    /**
     * Modifiers, T_CONST
     *
     * @var array<int,bool>
     */
    public array $NonMethodMember = [
        \T_CONST => true,
    ]
        + self::MODIFIER
        + self::TOKEN_INDEX;

    /**
     * T_FN, T_FUNCTION
     *
     * @var array<int,bool>
     */
    public array $FunctionOrFn = [
        \T_FN => true,
        \T_FUNCTION => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_IF, T_ELSEIF
     *
     * @var array<int,bool>
     */
    public array $IfOrElseIf = [
        \T_IF => true,
        \T_ELSEIF => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_IF, T_ELSEIF, T_ELSE
     *
     * @var array<int,bool>
     */
    public array $IfElseIfOrElse = [
        \T_IF => true,
        \T_ELSEIF => true,
        \T_ELSE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_COMMENT, T_DOC_COMMENT,
     * T_INLINE_HTML, T_WHITESPACE
     *
     * @var array<int,bool>
     */
    public array $NotCode = [
        \T_INLINE_HTML => true,
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
        \T_CLOSE_TAG => true,
        \T_WHITESPACE => true,
    ]
        + self::COMMENT
        + self::TOKEN_INDEX;

    /**
     * T_LNUMBER, T_DNUMBER
     *
     * @var array<int,bool>
     */
    public array $Number = [
        \T_LNUMBER => true,
        \T_DNUMBER => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * Arithmetic operators, assignment operators, bitwise operators, comparison
     * operators, logical operators, ternary operators, T_AT, T_CONCAT,
     * T_DOLLAR, T_DOUBLE_ARROW, T_INC, T_DEC, T_INSTANCEOF
     *
     * @var array<int,bool>
     */
    public array $Operator = [
        \T_AT => true,
        \T_CONCAT => true,
        \T_DOLLAR => true,
        \T_DOUBLE_ARROW => true,
        \T_INC => true,
        \T_DEC => true,
        \T_INSTANCEOF => true,
    ]
        + self::OPERATOR_ARITHMETIC
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_BITWISE
        + self::OPERATOR_COMPARISON
        + self::OPERATOR_LOGICAL
        + self::OPERATOR_TERNARY
        + self::TOKEN_INDEX;

    /**
     * Assignment operators
     *
     * @var array<int,bool>
     */
    public array $OperatorAssignment = self::OPERATOR_ASSIGNMENT
        + self::TOKEN_INDEX;

    /**
     * T_AND, T_OR, T_XOR, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND,
     * T_LOGICAL_OR, T_LOGICAL_XOR, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * Includes `&`, `|`, `^`, `&&`, `||`, `and`, `or` and `xor`.
     *
     * @var array<int,bool>
     */
    public array $OperatorBooleanExceptNot = [
        \T_OR => true,
        \T_XOR => true,
        \T_LOGICAL_NOT => false,
    ]
        + self::OPERATOR_LOGICAL
        + self::AMPERSAND
        + self::TOKEN_INDEX;

    /**
     * Comparison operators (except T_COALESCE)
     *
     * @var array<int,bool>
     */
    public array $OperatorComparison = [
        \T_COALESCE => false,
    ]
        + self::OPERATOR_COMPARISON
        + self::TOKEN_INDEX;

    /**
     * T_AT, T_DEC, T_DOLLAR, T_INC, T_LOGICAL_NOT, T_NOT
     *
     * @var array<int,bool>
     */
    public array $OperatorUnary = [
        \T_AT => true,
        \T_DOLLAR => true,
        \T_INC => true,
        \T_DEC => true,
        \T_LOGICAL_NOT => true,
        \T_NOT => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_PLUS, T_MINUS
     *
     * @var array<int,bool>
     */
    public array $PlusOrMinus = [
        \T_PLUS => true,
        \T_MINUS => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_RETURN, T_YIELD, T_YIELD_FROM
     *
     * @var array<int,bool>
     */
    public array $Return = [
        \T_RETURN => true,
        \T_YIELD => true,
        \T_YIELD_FROM => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_DOUBLE_QUOTE, T_START_HEREDOC, T_END_HEREDOC, T_BACKTICK
     *
     * @var array<int,bool>
     */
    public array $StringDelimiter = [
        \T_DOUBLE_QUOTE => true,
        \T_START_HEREDOC => true,
        \T_END_HEREDOC => true,
        \T_BACKTICK => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_OR
     *
     * @var array<int,bool>
     */
    public array $TypeDelimiter = [
        \T_OR => true,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_ARRAY, T_CALLABLE,
     * T_CLOSE_PARENTHESIS, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_OPEN_PARENTHESIS, T_OR, T_QUESTION, T_STATIC, T_STRING
     *
     * @var array<int,bool>
     */
    public array $ValueType = [
        \T_OR => true,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => true,
        \T_CLOSE_PARENTHESIS => true,
    ]
        + self::VALUE_TYPE_START
        + self::TOKEN_INDEX;

    /**
     * T_END_ALT_SYNTAX, T_NULL
     *
     * @var array<int,bool>
     */
    public array $Virtual = [
        \T_END_ALT_SYNTAX => true,
        \T_NULL => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * Visibility modifiers
     *
     * @var array<int,bool>
     */
    public array $Visibility = self::VISIBILITY
        + self::TOKEN_INDEX;

    /**
     * Visibility modifiers, T_READONLY
     *
     * @var array<int,bool>
     */
    public array $VisibilityOrReadonly = self::VISIBILITY + [
        \T_READONLY => true,
    ]
        + self::TOKEN_INDEX;

    // Context:

    /**
     * T_OPEN_BRACE, T_OPEN_PARENTHESIS, T_EXTENDS, T_IMPLEMENTS
     *
     * The token that appears after an anonymous `T_CLASS` or `T_FUNCTION`.
     *
     * @var array<int,bool>
     */
    public array $AfterAnonymousClassOrFunction = [
        \T_OPEN_BRACE => true,
        \T_OPEN_PARENTHESIS => true,
        \T_EXTENDS => true,
        \T_IMPLEMENTS => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ARRAY, T_CLASS, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS,
     * T_CONSTANT_ENCAPSED_STRING, T_DECLARE, T_DOUBLE_QUOTE, T_FN, T_FOR,
     * T_FUNCTION, T_ISSET, T_LIST, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_STATIC, T_STRING, T_UNSET, T_USE, T_VARIABLE
     *
     * Tokens that may appear before parentheses that enclose a delimited list.
     *
     * @var array<int,bool>
     */
    public array $BeforeListParenthesis = [
        \T_ARRAY => true,
        \T_CLASS => true,
        \T_DECLARE => true,
        \T_FN => true,
        \T_FOR => true,
        \T_FUNCTION => true,
        \T_ISSET => true,
        \T_LIST => true,
        \T_STATIC => true,
        \T_UNSET => true,
        \T_USE => true,
        \T_VARIABLE => true,
    ]
        + self::DEREFERENCEABLE_SCALAR_END
        + self::NAME
        + self::TOKEN_INDEX;

    /**
     * Arithmetic operators, assignment operators, bitwise operators, comparison
     * operators, logical operators, casts, keywords, T_AT, T_COMMA, T_CONCAT,
     * T_DOLLAR_OPEN_CURLY_BRACES, T_DOUBLE_ARROW, T_ELLIPSIS, T_OPEN_BRACE,
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_SEMICOLON
     *
     * Tokens that may appear before unary operators.
     *
     * @var array<int,bool>
     */
    public array $BeforeUnary = [
        \T_AT => true,
        \T_COMMA => true,
        \T_CONCAT => true,
        \T_DOLLAR_OPEN_CURLY_BRACES => true,
        \T_DOUBLE_ARROW => true,
        \T_ELLIPSIS => true,
        \T_OPEN_BRACE => true,
        \T_OPEN_BRACKET => true,
        \T_OPEN_PARENTHESIS => true,
        \T_SEMICOLON => true,
    ]
        + self::OPERATOR_ARITHMETIC
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_BITWISE
        + self::OPERATOR_COMPARISON
        + self::OPERATOR_LOGICAL
        + self::CAST
        + self::KEYWORD
        + self::TOKEN_INDEX;

    /**
     * T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OPEN_BRACE,
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_DOLLAR, T_STRING, T_VARIABLE
     *
     * @var array<int,bool>
     */
    public array $ChainPart = self::CHAIN_PART
        + self::TOKEN_INDEX;

    /**
     * T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_DOUBLE_QUOTE,
     * T_ENCAPSED_AND_WHITESPACE, T_ARRAY, T_CONSTANT_ENCAPSED_STRING,
     * T_DOUBLE_COLON, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_STATIC, T_DOLLAR, T_OPEN_BRACE, T_OPEN_BRACKET,
     * T_OPEN_PARENTHESIS, T_STRING, T_VARIABLE, T_OBJECT_OPERATOR,
     * T_NULLSAFE_OBJECT_OPERATOR, magic constants
     *
     * @var array<int,bool>
     */
    public array $ChainExpression = [
        // '"' ... '"'
        \T_CURLY_OPEN => true,
        \T_DOLLAR_OPEN_CURLY_BRACES => true,
        \T_DOUBLE_QUOTE => true,
        \T_ENCAPSED_AND_WHITESPACE => true,
        // Other dereferenceables
        \T_ARRAY => true,
        \T_CONSTANT_ENCAPSED_STRING => true,
        \T_DOUBLE_COLON => true,
        \T_NAME_FULLY_QUALIFIED => true,
        \T_NAME_QUALIFIED => true,
        \T_NAME_RELATIVE => true,
        \T_STATIC => true,
    ]
        + self::CHAIN_PART
        + self::MAGIC_CONSTANT
        + self::TOKEN_INDEX;

    /**
     * T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY
     *
     * Excludes `T_WHILE`, which only qualifies after `T_DO`. Check for this
     * separately.
     *
     * @var array<int,bool>
     */
    public array $ContinuesControlStructure = [
        \T_ELSEIF => true,
        \T_ELSE => true,
        \T_CATCH => true,
        \T_FINALLY => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ATTRIBUTE_COMMENT,
     * T_ATTRIBUTE, T_CASE, T_CLASS, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_IMPLEMENTS, T_INTERFACE,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAMESPACE,
     * T_NS_SEPARATOR, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC,
     * T_STRING, T_TRAIT, T_USE, T_VAR
     *
     * @var array<int,bool>
     */
    public array $DeclarationPart = self::DECLARATION_PART
        + self::TOKEN_INDEX;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ATTRIBUTE_COMMENT,
     * T_ATTRIBUTE, T_CASE, T_CLASS, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_IMPLEMENTS, T_INTERFACE,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAMESPACE,
     * T_NEW, T_NS_SEPARATOR, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY,
     * T_STATIC, T_STRING, T_TRAIT, T_USE, T_VAR
     *
     * @var array<int,bool>
     */
    public array $DeclarationPartWithNew = self::DECLARATION_PART + [
        \T_NEW => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ARRAY, T_ATTRIBUTE,
     * T_ATTRIBUTE_COMMENT, T_CALLABLE, T_CASE, T_CLASS, T_CLOSE_BRACE,
     * T_CLOSE_PARENTHESIS, T_COLON, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_IMPLEMENTS, T_INTERFACE, T_NAMESPACE,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NEW,
     * T_NS_SEPARATOR, T_OPEN_BRACE, T_OPEN_PARENTHESIS, T_OR, T_PRIVATE,
     * T_PROTECTED, T_PUBLIC, T_QUESTION, T_READONLY, T_STATIC, T_STRING,
     * T_TRAIT, T_USE, T_VAR
     *
     * @var array<int,bool>
     */
    public array $DeclarationPartWithNewAndBody = self::DECLARATION_PART + [
        \T_ARRAY => true,
        \T_CALLABLE => true,
        \T_COLON => true,
        \T_NEW => true,
        \T_OPEN_BRACE => true,
        \T_CLOSE_BRACE => true,
        \T_OPEN_PARENTHESIS => true,
        \T_CLOSE_PARENTHESIS => true,
        \T_OR => true,
        \T_QUESTION => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS,
     * T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTE, T_NAME_FULLY_QUALIFIED,
     * T_NAME_QUALIFIED, T_NAME_RELATIVE, T_STRING, T_STRING_VARNAME,
     * T_VARIABLE, T_CLASS_C, T_DIR, T_FILE, T_FUNC_C, T_LINE, T_METHOD_C,
     * T_NS_C, T_PROPERTY_C, T_TRAIT_C
     *
     * @var array<int,bool>
     */
    public array $EndOfDereferenceable = self::DEREFERENCEABLE_END
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_STRING, T_VARIABLE
     *
     * @var array<int,bool>
     */
    public array $EndOfVariable = [
        \T_CLOSE_BRACE => true,
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
        \T_STRING => true,
        \T_VARIABLE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_AND, T_STRING, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * @var array<int,bool>
     */
    public array $FunctionIdentifier = [
        \T_STRING => true,
    ]
        + self::AMPERSAND
        + self::TOKEN_INDEX;

    /**
     * T_CATCH, T_DECLARE, T_DO, T_ELSE, T_ELSEIF, T_FINALLY, T_FOR, T_FOREACH,
     * T_IF, T_SWITCH, T_TRY, T_WHILE
     *
     * @var array<int,bool>
     */
    public array $HasStatement = self::HAS_STATEMENT_WITH_OPTIONAL_BRACES
        + self::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES
        + self::HAS_STATEMENT_WITH_BRACES
        + self::HAS_EXPRESSION_AND_STATEMENT_WITH_BRACES
        + self::TOKEN_INDEX;

    /**
     * T_DO, T_ELSE
     *
     * @var array<int,bool>
     */
    public array $HasStatementWithOptionalBraces = self::HAS_STATEMENT_WITH_OPTIONAL_BRACES
        + self::TOKEN_INDEX;

    /**
     * T_ELSEIF, T_FOR, T_FOREACH, T_IF, T_WHILE
     *
     * @var array<int,bool>
     */
    public array $HasExpressionAndStatementWithOptionalBraces = self::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES
        + self::TOKEN_INDEX;

    /**
     * T_ARRAY, T_CALLABLE, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_OPEN_PARENTHESIS, T_QUESTION, T_STATIC, T_STRING
     *
     * @var array<int,bool>
     */
    public array $StartOfValueType = self::VALUE_TYPE_START
        + self::TOKEN_INDEX;

    /**
     * T_CASE, T_DEFAULT, T_COLON, T_SEMICOLON, T_CLOSE_TAG
     *
     * @var array<int,bool>
     */
    public array $SwitchCaseOrDelimiter = [
        \T_CASE => true,
        \T_DEFAULT => true,
        \T_COLON => true,
        \T_SEMICOLON => true,
        \T_CLOSE_TAG => true,
    ]
        + self::TOKEN_INDEX;

    // Parsing:

    /**
     * T_COLON, T_COMMA, T_SEMICOLON
     *
     * @var array<int,bool>
     */
    public array $EndOfStatement = [
        \T_COLON => true,
        \T_COMMA => true,
        \T_SEMICOLON => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * Assignment operators, comparison operators (except T_COALESCE),
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_SEMICOLON, T_DOUBLE_ARROW
     *
     * @var array<int,bool>
     */
    public array $EndOfExpression = [
        \T_CLOSE_BRACKET => true,
        \T_CLOSE_PARENTHESIS => true,
        \T_SEMICOLON => true,
        \T_DOUBLE_ARROW => true,
        \T_COALESCE => false,
    ]
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_COMPARISON
        + self::TOKEN_INDEX;

    /**
     * Assignment operators, comparison operators (except T_COALESCE),
     * T_DOUBLE_ARROW
     *
     * @var array<int,bool>
     */
    public array $ExpressionDelimiter = [
        \T_DOUBLE_ARROW => true,
        \T_COALESCE => false,
    ]
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_COMPARISON
        + self::TOKEN_INDEX;

    /**
     * Assignment operators, T_DOUBLE_ARROW
     *
     * @var array<int,bool>
     */
    public array $ExpressionDelimiterExceptComparison = [
        \T_DOUBLE_ARROW => true,
    ]
        + self::OPERATOR_ASSIGNMENT
        + self::TOKEN_INDEX;

    // Formatting:

    /**
     * T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML
     *
     * @var array<int,bool>
     */
    public array $NotTrimmable = self::NOT_TRIMMABLE
        + self::TOKEN_INDEX;

    /**
     * T_CLOSE_TAG, T_START_HEREDOC
     *
     * @var array<int,bool>
     */
    public array $LeftTrimmable = self::LEFT_TRIMMABLE
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_END_HEREDOC
     *
     * @var array<int,bool>
     */
    public array $RightTrimmable = self::RIGHT_TRIMMABLE
        + self::TOKEN_INDEX;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_START_HEREDOC,
     * T_END_HEREDOC, T_COMMENT, T_DOC_COMMENT, T_ATTRIBUTE_COMMENT,
     * T_WHITESPACE
     *
     * Includes left- and right-trimmable tokens.
     *
     * @var array<int,bool>
     */
    public array $Trimmable = [
        \T_ATTRIBUTE_COMMENT => true,
        \T_WHITESPACE => true,
    ]
        + self::COMMENT
        + self::LEFT_TRIMMABLE
        + self::RIGHT_TRIMMABLE
        + self::TOKEN_INDEX;

    /**
     * Tokens that may appear after a newline
     *
     * @prettyphp-dynamic
     *
     * @var array<int,bool>
     */
    public array $AllowNewlineBefore;

    /**
     * Tokens that may appear before a newline
     *
     * @prettyphp-dynamic
     *
     * @var array<int,bool>
     */
    public array $AllowNewlineAfter;

    /**
     * Tokens that may appear after a blank line
     *
     * @prettyphp-dynamic
     *
     * @var array<int,bool>
     */
    public array $PreserveBlankBefore = [
        \T_CLOSE_TAG => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * Tokens that may appear before a blank line
     *
     * @prettyphp-dynamic
     *
     * @var array<int,bool>
     */
    public array $PreserveBlankAfter = [
        \T_CLOSE_BRACE => true,
        \T_COMMA => true,
        \T_COMMENT => true,
        \T_DOC_COMMENT => true,
        \T_OPEN_TAG => true,
        \T_OPEN_TAG_WITH_ECHO => true,
        \T_SEMICOLON => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_AS, T_FUNCTION, T_INSTEADOF, T_USE
     *
     * Tokens that require a leading and trailing space.
     *
     * @var array<int,bool>
     */
    public array $AddSpace = [
        \T_AS => true,
        \T_FUNCTION => true,
        \T_INSTEADOF => true,
        \T_USE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_ARRAY, T_CALLABLE, T_ELLIPSIS, T_EXTENDS, T_FN, T_GLOBAL, T_IMPLEMENTS,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE,
     * T_NS_SEPARATOR, T_STATIC, T_STRING, T_VARIABLE, T_ABSTRACT, T_CONST,
     * T_DECLARE, T_FINAL, T_READONLY, T_VAR, T_CLASS, T_ENUM, T_INTERFACE,
     * T_TRAIT, T_PRIVATE, T_PROTECTED, T_PUBLIC
     *
     * Tokens that require a leading space.
     *
     * @var array<int,bool>
     */
    public array $AddSpaceBefore = [
        \T_ARRAY => true,
        \T_CALLABLE => true,
        \T_ELLIPSIS => true,
        \T_EXTENDS => true,
        \T_FN => true,
        \T_GLOBAL => true,
        \T_IMPLEMENTS => true,
        \T_NAME_FULLY_QUALIFIED => true,
        \T_NAME_QUALIFIED => true,
        \T_NAME_RELATIVE => true,
        \T_NS_SEPARATOR => true,
        \T_STATIC => true,
        \T_STRING => true,
        \T_VARIABLE => true,
    ]
        + self::DECLARATION_ONLY
        + self::TOKEN_INDEX;

    /**
     * T_BREAK, T_CASE, T_CATCH, T_CLONE, T_CONTINUE, T_ECHO, T_ELSE, T_ELSEIF,
     * T_FOR, T_FOREACH, T_GOTO, T_IF, T_INCLUDE, T_INCLUDE_ONCE, T_MATCH,
     * T_NEW, T_PRINT, T_REQUIRE, T_REQUIRE_ONCE, T_RETURN, T_SWITCH, T_THROW,
     * T_WHILE, T_YIELD, T_YIELD_FROM, casts
     *
     * Tokens that require a trailing space.
     *
     * @var array<int,bool>
     */
    public array $AddSpaceAfter = [
        \T_BREAK => true,
        \T_CASE => true,
        \T_CATCH => true,
        \T_CLONE => true,
        \T_CONTINUE => true,
        \T_ECHO => true,
        \T_ELSE => true,
        \T_ELSEIF => true,
        \T_FOR => true,
        \T_FOREACH => true,
        \T_GOTO => true,
        \T_IF => true,
        \T_INCLUDE => true,
        \T_INCLUDE_ONCE => true,
        \T_MATCH => true,
        \T_NEW => true,
        \T_PRINT => true,
        \T_REQUIRE => true,
        \T_REQUIRE_ONCE => true,
        \T_RETURN => true,
        \T_SWITCH => true,
        \T_THROW => true,
        \T_WHILE => true,
        \T_YIELD => true,
        \T_YIELD_FROM => true,
    ]
        + self::CAST
        + self::TOKEN_INDEX;

    /**
     * T_NS_SEPARATOR
     *
     * Tokens that must not render with a leading space.
     *
     * @var array<int,bool>
     */
    public array $SuppressSpaceBefore = [
        \T_NS_SEPARATOR => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_DOUBLE_COLON, T_ELLIPSIS, T_NS_SEPARATOR, T_OBJECT_OPERATOR,
     * T_NULLSAFE_OBJECT_OPERATOR
     *
     * Tokens that must not render with a trailing space.
     *
     * @var array<int,bool>
     */
    public array $SuppressSpaceAfter = [
        \T_DOUBLE_COLON => true,
        \T_ELLIPSIS => true,
        \T_NS_SEPARATOR => true,
        \T_OBJECT_OPERATOR => true,
        \T_NULLSAFE_OBJECT_OPERATOR => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_USE
     *
     * @var array<int,bool>
     */
    public array $SuppressBlankBetween = [
        \T_USE => true,
    ]
        + self::TOKEN_INDEX;

    /**
     * T_DECLARE
     *
     * @var array<int,bool>
     */
    public array $SuppressBlankBetweenOneLine = [
        \T_DECLARE => true,
    ]
        + self::TOKEN_INDEX;

    // Filtering:

    /**
     * Casts, T_ATTRIBUTE_COMMENT, T_CONSTANT_ENCAPSED_STRING,
     * T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML, T_OPEN_TAG, T_START_HEREDOC,
     * T_END_HEREDOC, T_WHITESPACE, T_YIELD_FROM, T_COMMENT, T_DOC_COMMENT
     *
     * Tokens that may contain tab characters.
     *
     * @var array<int,bool>
     */
    public array $Expandable = [
        \T_ATTRIBUTE_COMMENT => true,
        \T_CONSTANT_ENCAPSED_STRING => true,
        \T_ENCAPSED_AND_WHITESPACE => true,
        \T_INLINE_HTML => true,
        \T_OPEN_TAG => true,
        \T_START_HEREDOC => true,
        \T_END_HEREDOC => true,
        \T_WHITESPACE => true,
        \T_YIELD_FROM => true,
    ]
        + self::CAST
        + self::COMMENT
        + self::TOKEN_INDEX;

    /**
     * Arithmetic operators, assignment operators, bitwise operators, comparison
     * operators, logical operators, ternary operators, T_COMMA, T_CONCAT,
     * T_SEMICOLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR
     *
     * Tokens that may be swapped with adjacent comment tokens for correct
     * placement.
     *
     * @var array<int,bool>
     */
    public array $Movable = [
        \T_COMMA => true,
        \T_CONCAT => true,
        // \T_DOUBLE_ARROW => true,
        \T_SEMICOLON => true,
    ]
        + self::CHAIN
        + self::OPERATOR_ARITHMETIC
        + self::OPERATOR_ASSIGNMENT
        + self::OPERATOR_BITWISE
        + self::OPERATOR_COMPARISON
        + self::OPERATOR_LOGICAL
        + self::OPERATOR_TERNARY
        + self::TOKEN_INDEX;

    /** @var self::FIRST|self::LAST|self::MIXED */
    protected int $Operators;

    public function __construct(
        bool $operatorsFirst = false,
        bool $operatorsLast = false
    ) {
        $operators = $operatorsFirst
            ? self::FIRST
            : ($operatorsLast ? self::LAST : self::MIXED);

        if ($operators === self::FIRST) {
            [$before, $after] = $this->getOperatorsFirstIndexes();
        } elseif ($operators === self::LAST) {
            [$before, $after] = $this->getOperatorsLastIndexes();
        }

        $this->AllowNewlineBefore = $before ?? self::DEFAULT_ALLOW_NEWLINE_BEFORE;
        $this->AllowNewlineAfter = $after ?? self::DEFAULT_ALLOW_NEWLINE_AFTER;
        $this->Operators = $operators;
    }

    /**
     * @return static
     */
    public function withLeadingOperators()
    {
        [$before, $after] = $this->getOperatorsFirstIndexes();
        return $this->with('AllowNewlineBefore', $before)
                    ->with('AllowNewlineAfter', $after)
                    ->with('Operators', self::FIRST);
    }

    /**
     * @return array{array<int,bool>,array<int,bool>}
     */
    private function getOperatorsFirstIndexes(): array
    {
        $both = self::intersect(
            self::DEFAULT_ALLOW_NEWLINE_BEFORE,
            self::DEFAULT_ALLOW_NEWLINE_AFTER,
        );

        $before = self::OPERATOR_COMPARISON
            + self::OPERATOR_LOGICAL
            + self::DEFAULT_ALLOW_NEWLINE_BEFORE;

        $after = self::merge(
            self::diff(
                self::DEFAULT_ALLOW_NEWLINE_AFTER,
                $before,
            ),
            $both
        );

        return [$before, $after];
    }

    /**
     * @return static
     */
    public function withTrailingOperators()
    {
        [$before, $after] = $this->getOperatorsLastIndexes();
        return $this->with('AllowNewlineBefore', $before)
                    ->with('AllowNewlineAfter', $after)
                    ->with('Operators', self::LAST);
    }

    /**
     * @return array{array<int,bool>,array<int,bool>}
     */
    private function getOperatorsLastIndexes(): array
    {
        $both = self::intersect(
            self::DEFAULT_ALLOW_NEWLINE_BEFORE,
            self::DEFAULT_ALLOW_NEWLINE_AFTER,
        );

        $after = [
            \T_COALESCE => true,
            \T_CONCAT => true,
        ]
            + self::OPERATOR_ARITHMETIC
            + self::OPERATOR_BITWISE
            + self::DEFAULT_ALLOW_NEWLINE_AFTER;

        $before = self::merge(
            self::diff(
                self::DEFAULT_ALLOW_NEWLINE_BEFORE,
                $after,
            ),
            $both
        );

        return [$before, $after];
    }

    /**
     * @return static
     */
    public function withMixedOperators()
    {
        return $this->with('AllowNewlineBefore', self::DEFAULT_ALLOW_NEWLINE_BEFORE)
                    ->with('AllowNewlineAfter', self::DEFAULT_ALLOW_NEWLINE_AFTER)
                    ->with('Operators', self::MIXED);
    }

    /**
     * @return static
     */
    public function withoutPreserveNewline()
    {
        return $this->with('AllowNewlineBefore', $this->PreserveBlankBefore)
                    ->with('AllowNewlineAfter', $this->PreserveBlankAfter);
    }

    /**
     * @return static
     */
    public function withPreserveNewline()
    {
        switch ($this->Operators) {
            case self::FIRST:
                return $this->withLeadingOperators();
            case self::LAST:
                return $this->withTrailingOperators();
            case self::MIXED:
                return $this->withMixedOperators();
        }
    }

    /**
     * Get an index of the given token types
     *
     * @return array<int,bool>
     */
    final public static function get(int ...$types): array
    {
        return array_fill_keys($types, true) + self::TOKEN_INDEX;
    }

    /**
     * Get an index of every token type in the given indexes
     *
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    final public static function merge(array ...$indexes): array
    {
        $index = self::TOKEN_INDEX;
        foreach ($indexes as $idx) {
            $index = array_filter($idx) + $index;
        }
        return $index;
    }

    /**
     * Get an index of every token type in a given index that is not present in
     * any of the others
     *
     * @param array<int,bool> $index
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    final public static function diff(array $index, array ...$indexes): array
    {
        if (!$indexes) {
            // @codeCoverageIgnoreStart
            return $index + self::TOKEN_INDEX;
            // @codeCoverageIgnoreEnd
        }
        $index = array_filter($index);
        foreach ($indexes as $idx) {
            $filtered[] = array_filter($idx);
        }
        return array_diff_key($index, ...$filtered) + self::TOKEN_INDEX;
    }

    /**
     * Get an index of every token type in a given index that is present in all
     * of the others
     *
     * @param array<int,bool> $index
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    final public static function intersect(array $index, array ...$indexes): array
    {
        if (!$indexes) {
            // @codeCoverageIgnoreStart
            return $index + self::TOKEN_INDEX;
            // @codeCoverageIgnoreEnd
        }
        $index = array_filter($index);
        foreach ($indexes as $idx) {
            $filtered[] = array_filter($idx);
        }
        return array_intersect_key($index, ...$filtered) + self::TOKEN_INDEX;
    }
}
