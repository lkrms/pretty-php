<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\PrettyPHP\Catalog\TokenGroup as TG;
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

    private const LEADING = 0;
    private const TRAILING = 1;
    private const MIXED = 2;

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_CLOSE_BRACE,
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_ATTRIBUTE, T_CURLY_OPEN,
     * T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Bracket;

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE,
     * T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBracket;

    /**
     * T_OPEN_BRACE, T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE,
     * T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_LOGICAL_NOT, T_NOT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBracketOrNot;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracket;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_END_ALT_SYNTAX
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracketOrEndAltSyntax;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_COMMA
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracketOrComma;

    /**
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE
     *
     * Also excludes `T_CURLY_OPEN` and `T_DOLLAR_OPEN_CURLY_BRACES`.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBracketExceptBrace;

    /**
     * T_OPEN_BRACE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBrace;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenTag;

    /**
     * T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY
     *
     * Excludes `T_WHILE`, which only qualifies after `T_DO`.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ContinuesControlStructure;

    /**
     * T_ABSTRACT, T_CONST, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC,
     * T_READONLY, T_STATIC, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $NonMethodMember;

    /**
     * T_ATTRIBUTE_COMMENT, T_COMMENT, T_CONSTANT_ENCAPSED_STRING,
     * T_DOC_COMMENT, T_ENCAPSED_AND_WHITESPACE, T_END_HEREDOC, T_INLINE_HTML,
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_START_HEREDOC, T_WHITESPACE
     *
     * Tokens that may contain tab characters.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Expandable;

    /**
     * T_AND, T_AND_EQUAL, T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE,
     * T_COALESCE_EQUAL, T_CONCAT, T_CONCAT_EQUAL, T_DIV, T_DIV_EQUAL,
     * T_GREATER, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL,
     * T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_LOGICAL_AND,
     * T_LOGICAL_OR, T_LOGICAL_XOR, T_MINUS, T_MINUS_EQUAL, T_MOD, T_MOD_EQUAL,
     * T_MUL, T_MUL_EQUAL, T_NOT, T_OR, T_OR_EQUAL, T_PLUS, T_PLUS_EQUAL, T_POW,
     * T_POW_EQUAL, T_SL, T_SL_EQUAL, T_SMALLER, T_SPACESHIP, T_SR, T_SR_EQUAL,
     * T_XOR, T_XOR_EQUAL, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * Tokens that may be swapped with adjacent comment tokens when operator
     * placement is changed.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Movable;

    /**
     * T_AND, T_AND_EQUAL, T_BOOLEAN_AND, T_BOOLEAN_OR, T_CLOSE_BRACE,
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_CLOSE_TAG, T_COALESCE,
     * T_COALESCE_EQUAL, T_COLON, T_COMMA, T_CONCAT, T_CONCAT_EQUAL, T_DIV,
     * T_DIV_EQUAL, T_DOUBLE_ARROW, T_EQUAL, T_GREATER, T_IS_EQUAL,
     * T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL,
     * T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_LOGICAL_AND, T_LOGICAL_NOT,
     * T_LOGICAL_OR, T_LOGICAL_XOR, T_MINUS, T_MINUS_EQUAL, T_MOD, T_MOD_EQUAL,
     * T_MUL, T_MUL_EQUAL, T_NOT, T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR,
     * T_OR, T_OR_EQUAL, T_PLUS, T_PLUS_EQUAL, T_POW, T_POW_EQUAL, T_QUESTION,
     * T_SEMICOLON, T_SL, T_SL_EQUAL, T_SMALLER, T_SPACESHIP, T_SR, T_SR_EQUAL,
     * T_XOR, T_XOR_EQUAL, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * Tokens where a preceding DocBlock can be demoted to a standard C-style
     * comment without loss of information.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Undocumentable;

    /**
     * T_LNUMBER, T_DNUMBER
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Number;

    /**
     * T_DOUBLE_QUOTE, T_START_HEREDOC, T_END_HEREDOC, T_BACKTICK
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $StringDelimiter;

    /**
     * T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModify;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_END_HEREDOC
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModifyLeft;

    /**
     * T_CLOSE_TAG, T_START_HEREDOC
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModifyRight;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_START_HEREDOC,
     * T_END_HEREDOC, T_COMMENT, T_DOC_COMMENT, T_ATTRIBUTE_COMMENT,
     * T_WHITESPACE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Trim;

    /**
     * T_USE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressBlankBetween;

    /**
     * T_DECLARE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressBlankBetweenOneLine;

    /**
     * Tokens that require leading and trailing spaces
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAround;

    /**
     * Tokens that require leading spaces
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceBefore;

    /**
     * Tokens that require trailing spaces
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAfter;

    /**
     * Tokens that require suppression of leading spaces
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressSpaceBefore;

    /**
     * Tokens that require suppression of trailing spaces
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressSpaceAfter;

    /**
     * Tokens that may appear after a newline
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveNewlineBefore;

    /**
     * Tokens that may appear before a newline
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveNewlineAfter;

    /**
     * Tokens that may appear after a blank line
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveBlankBefore;

    /**
     * Tokens that may appear before a blank line
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveBlankAfter;

    /**
     * T_COLON, T_COMMA, T_SEMICOLON
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $StatementTerminator;

    /**
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_SEMICOLON, T_DOUBLE_ARROW,
     * T_AND_EQUAL, T_COALESCE_EQUAL, T_CONCAT_EQUAL, T_DIV_EQUAL, T_EQUAL,
     * T_GREATER, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL,
     * T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_MINUS_EQUAL,
     * T_MOD_EQUAL, T_MUL_EQUAL, T_OR_EQUAL, T_PLUS_EQUAL, T_POW_EQUAL,
     * T_SL_EQUAL, T_SMALLER, T_SPACESHIP, T_SR_EQUAL, T_XOR_EQUAL
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ExpressionTerminator;

    /**
     * T_DOUBLE_ARROW, T_AND_EQUAL, T_COALESCE_EQUAL, T_CONCAT_EQUAL,
     * T_DIV_EQUAL, T_EQUAL, T_GREATER, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL,
     * T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL,
     * T_IS_SMALLER_OR_EQUAL, T_MINUS_EQUAL, T_MOD_EQUAL, T_MUL_EQUAL,
     * T_OR_EQUAL, T_PLUS_EQUAL, T_POW_EQUAL, T_SL_EQUAL, T_SMALLER,
     * T_SPACESHIP, T_SR_EQUAL, T_XOR_EQUAL
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ExpressionDelimiter;

    /**
     * T_AND_EQUAL, T_COALESCE_EQUAL, T_CONCAT_EQUAL, T_DIV_EQUAL,
     * T_DOUBLE_ARROW, T_EQUAL, T_MINUS_EQUAL, T_MOD_EQUAL, T_MUL_EQUAL,
     * T_OR_EQUAL, T_PLUS_EQUAL, T_POW_EQUAL, T_SL_EQUAL, T_SR_EQUAL,
     * T_XOR_EQUAL
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ExpressionDelimiterExceptComparison;

    /**
     * T_AND, T_STRING, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $FunctionIdentifier;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS,
     * T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTE, T_NAME_FULLY_QUALIFIED,
     * T_NAME_QUALIFIED, T_NAME_RELATIVE, T_STRING, T_STRING_VARNAME,
     * T_VARIABLE, T_CLASS_C, T_DIR, T_FILE, T_FUNC_C, T_LINE, T_METHOD_C,
     * T_NS_C, T_PROPERTY_C, T_TRAIT_C
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DereferenceableTerminator;

    /**
     * T_COLON, T_SEMICOLON, T_CLOSE_TAG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SwitchCaseDelimiter;

    /**
     * T_CASE, T_DEFAULT, T_COLON, T_SEMICOLON, T_CLOSE_TAG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SwitchCaseOrDelimiter;

    /**
     * T_ARRAY, T_CLASS, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS,
     * T_CONSTANT_ENCAPSED_STRING, T_DECLARE, T_DOUBLE_QUOTE, T_FN, T_FOR,
     * T_FUNCTION, T_ISSET, T_LIST, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_STATIC, T_STRING, T_UNSET, T_USE, T_VARIABLE
     *
     * Tokens that may appear before open parentheses with delimited lists.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ListParenthesisPredecessor;

    /**
     * Tokens that may appear before unary operators
     *
     * @internal
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $UnaryPredecessor;

    /**
     * T_DECLARE, T_FOR, T_FOREACH, T_IF, T_SWITCH, T_WHILE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxStart;

    /**
     * T_ELSE, T_ELSEIF
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxContinue;

    /**
     * T_ELSEIF
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxContinueWithExpression;

    /**
     * T_ELSE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxContinueWithoutExpression;

    /**
     * T_ENDDECLARE, T_ENDFOR, T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxEnd;

    /**
     * T_AND, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Ampersand;

    /**
     * T_CLASS, T_FUNCTION
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ClassOrFunction;

    /**
     * T_OPEN_BRACE, T_OPEN_PARENTHESIS, T_EXTENDS, T_IMPLEMENTS
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AfterAnonymousClassOrFunction;

    /**
     * T_COMMA, T_DOUBLE_ARROW
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $CommaOrDoubleArrow;

    /**
     * T_FN, T_FUNCTION
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $FunctionOrFn;

    /**
     * T_IF, T_ELSEIF
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $IfOrElseIf;

    /**
     * T_IF, T_ELSEIF, T_ELSE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $IfElseIfOrElse;

    /**
     * T_PLUS, T_MINUS
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $PlusOrMinus;

    /**
     * T_ATTRIBUTE, T_ATTRIBUTE_COMMENT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Attribute;

    /**
     * T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST,
     * T_STRING_CAST, T_UNSET_CAST
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Cast;

    /**
     * T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Chain;

    /**
     * T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OPEN_BRACE,
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_DOLLAR, T_STRING, T_VARIABLE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ChainPart;

    /**
     * T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OPEN_BRACE,
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_DOLLAR, T_STRING, T_VARIABLE,
     * T_ARRAY, T_CONSTANT_ENCAPSED_STRING, T_CURLY_OPEN,
     * T_DOLLAR_OPEN_CURLY_BRACES, T_DOUBLE_COLON, T_DOUBLE_QUOTE,
     * T_ENCAPSED_AND_WHITESPACE, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_STATIC, T_CLASS_C, T_DIR, T_FILE, T_FUNC_C, T_LINE,
     * T_METHOD_C, T_NS_C, T_PROPERTY_C, T_TRAIT_C
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ChainExpression;

    /**
     * T_COMMENT, T_DOC_COMMENT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Comment;

    /**
     * T_ABSTRACT, T_CASE, T_CLASS, T_CONST, T_DECLARE, T_ENUM, T_FINAL,
     * T_FUNCTION, T_INTERFACE, T_NAMESPACE, T_PRIVATE, T_PROTECTED, T_PUBLIC,
     * T_READONLY, T_STATIC, T_TRAIT, T_USE, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Declaration;

    /**
     * T_CASE, T_CLASS, T_CONST, T_DECLARE, T_ENUM, T_FUNCTION, T_INTERFACE,
     * T_NAMESPACE, T_TRAIT, T_USE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationExceptModifiers;

    /**
     * T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationClass;

    /**
     * T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_TRAIT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationClassOrFunction;

    /**
     * T_COMMA, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE,
     * T_STATIC, T_STRING
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationList;

    /**
     * T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPropertyOrVariable;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ATTRIBUTE_COMMENT,
     * T_ATTRIBUTE, T_CASE, T_CLASS, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_IMPLEMENTS, T_INTERFACE,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAMESPACE,
     * T_NS_SEPARATOR, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC,
     * T_STRING, T_TRAIT, T_USE, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPart;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ATTRIBUTE_COMMENT,
     * T_ATTRIBUTE, T_CASE, T_CLASS, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_IMPLEMENTS, T_INTERFACE,
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAMESPACE,
     * T_NEW, T_NS_SEPARATOR, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY,
     * T_STATIC, T_STRING, T_TRAIT, T_USE, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPartWithNew;

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
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPartWithNewAndBody;

    /**
     * T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_NAMESPACE, T_TRAIT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationTopLevel;

    /**
     * T_CATCH, T_DECLARE, T_DO, T_ELSE, T_ELSEIF, T_FINALLY, T_FOR, T_FOREACH,
     * T_IF, T_SWITCH, T_TRY, T_WHILE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $HasStatement;

    /**
     * T_DO, T_ELSE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $HasStatementWithOptionalBraces;

    /**
     * T_ELSEIF, T_FOR, T_FOREACH, T_IF, T_WHILE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $HasExpressionAndStatementWithOptionalBraces;

    /**
     * T_ABSTRACT, T_ARRAY, T_AS, T_BREAK, T_CALLABLE, T_CASE, T_CATCH, T_CLASS,
     * T_CLASS_C, T_CLONE, T_CONST, T_CONTINUE, T_DECLARE, T_DEFAULT, T_DIR,
     * T_DO, T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY, T_ENDDECLARE, T_ENDFOR,
     * T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE, T_ENUM, T_EVAL, T_EXIT,
     * T_EXTENDS, T_FILE, T_FINAL, T_FINALLY, T_FN, T_FOR, T_FOREACH,
     * T_FUNCTION, T_FUNC_C, T_GLOBAL, T_GOTO, T_HALT_COMPILER, T_IF,
     * T_IMPLEMENTS, T_INCLUDE, T_INCLUDE_ONCE, T_INSTANCEOF, T_INSTEADOF,
     * T_INTERFACE, T_ISSET, T_LINE, T_LIST, T_LOGICAL_AND, T_LOGICAL_OR,
     * T_LOGICAL_XOR, T_MATCH, T_METHOD_C, T_NAMESPACE, T_NEW, T_NS_C, T_PRINT,
     * T_PRIVATE, T_PROPERTY_C, T_PROTECTED, T_PUBLIC, T_READONLY, T_REQUIRE,
     * T_REQUIRE_ONCE, T_RETURN, T_STATIC, T_STRING, T_SWITCH, T_THROW, T_TRAIT,
     * T_TRAIT_C, T_TRY, T_UNSET, T_USE, T_VAR, T_WHILE, T_YIELD
     *
     * identifier_maybe_reserved
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $MaybeReserved;

    /**
     * T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_COMMENT, T_DOC_COMMENT,
     * T_INLINE_HTML, T_WHITESPACE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $NotCode;

    /**
     * T_AND, T_AND_EQUAL, T_AT, T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE,
     * T_COALESCE_EQUAL, T_COLON, T_CONCAT, T_CONCAT_EQUAL, T_DEC, T_DIV,
     * T_DIV_EQUAL, T_DOLLAR, T_DOUBLE_ARROW, T_EQUAL, T_GREATER, T_INC,
     * T_INSTANCEOF, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL,
     * T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_LOGICAL_AND,
     * T_LOGICAL_NOT, T_LOGICAL_OR, T_LOGICAL_XOR, T_MINUS, T_MINUS_EQUAL,
     * T_MOD, T_MOD_EQUAL, T_MUL, T_MUL_EQUAL, T_NOT, T_OR, T_OR_EQUAL, T_PLUS,
     * T_PLUS_EQUAL, T_POW, T_POW_EQUAL, T_QUESTION, T_SL, T_SL_EQUAL,
     * T_SMALLER, T_SPACESHIP, T_SR, T_SR_EQUAL, T_XOR, T_XOR_EQUAL,
     * T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Operator;

    /**
     * T_AND_EQUAL, T_COALESCE_EQUAL, T_CONCAT_EQUAL, T_DIV_EQUAL, T_EQUAL,
     * T_MINUS_EQUAL, T_MOD_EQUAL, T_MUL_EQUAL, T_OR_EQUAL, T_PLUS_EQUAL,
     * T_POW_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_XOR_EQUAL
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OperatorAssignment;

    /**
     * T_AND, T_OR, T_XOR, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND,
     * T_LOGICAL_OR, T_LOGICAL_XOR, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
     *
     * `&`, `|`, `^`, `&&`, `||`, `and`, `or`, `xor`
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OperatorBooleanExceptNot;

    /**
     * T_GREATER, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL,
     * T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_SMALLER,
     * T_SPACESHIP
     *
     * Excludes `T_COALESCE`.
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OperatorComparison;

    /**
     * T_AT, T_DEC, T_DOLLAR, T_INC, T_LOGICAL_NOT, T_NOT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OperatorUnary;

    /**
     * T_RETURN, T_YIELD, T_YIELD_FROM
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Return;

    /**
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_OR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $TypeDelimiter;

    /**
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_ARRAY, T_CALLABLE,
     * T_CLOSE_PARENTHESIS, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_OPEN_PARENTHESIS, T_OR, T_QUESTION, T_STATIC, T_STRING
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ValueType;

    /**
     * T_ARRAY, T_CALLABLE, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED,
     * T_NAME_RELATIVE, T_OPEN_PARENTHESIS, T_QUESTION, T_STATIC, T_STRING
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ValueTypeStart;

    /**
     * T_CLOSE_BRACE, T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS, T_STRING, T_VARIABLE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $VariableEnd;

    /**
     * T_PRIVATE, T_PROTECTED, T_PUBLIC
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Visibility;

    /**
     * T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $VisibilityWithReadonly;

    /**
     * T_END_ALT_SYNTAX, T_NULL
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Virtual;

    /** @var self::LEADING|self::TRAILING|self::MIXED */
    private int $LastOperators;
    /** @var array<int,bool> */
    private array $_PreserveNewlineBefore;
    /** @var array<int,bool> */
    private array $_PreserveNewlineAfter;

    public function __construct()
    {
        $this->Bracket = self::get(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_ATTRIBUTE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->OpenBracket = self::get(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_ATTRIBUTE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->OpenBracketOrNot = self::get(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_ATTRIBUTE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
            \T_LOGICAL_NOT,
            \T_NOT,
        );

        $this->CloseBracket = self::get(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
        );

        $this->CloseBracketOrEndAltSyntax = self::get(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_END_ALT_SYNTAX,
        );

        $this->CloseBracketOrComma = self::get(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_COMMA,
        );

        $this->OpenBracketExceptBrace = self::get(
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_ATTRIBUTE,
        );

        $this->OpenBrace = self::get(
            \T_OPEN_BRACE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->OpenTag = self::get(
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
        );

        $this->ContinuesControlStructure = self::get(
            \T_ELSEIF,
            \T_ELSE,
            \T_CATCH,
            \T_FINALLY,
        );

        $this->NonMethodMember = self::get(
            \T_CONST,
            \T_VAR,
            ...TG::KEYWORD_MODIFIER,
        );

        $this->Expandable = self::get(
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
            \T_COMMENT,
            \T_DOC_COMMENT,
            \T_ATTRIBUTE_COMMENT,
            \T_CONSTANT_ENCAPSED_STRING,
            \T_ENCAPSED_AND_WHITESPACE,
            \T_START_HEREDOC,
            \T_END_HEREDOC,
            \T_INLINE_HTML,
            \T_WHITESPACE,
        );

        $this->Movable = self::get(
            \T_CONCAT,
            ...TG::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL,
            ...TG::OPERATOR_COMPARISON,
            ...TG::OPERATOR_LOGICAL_EXCEPT_NOT,
            ...TG::OPERATOR_ARITHMETIC,
            ...TG::OPERATOR_BITWISE,
        );

        // Derived from operators in `$this->PreserveNewlineBefore` and
        // `$this->PreserveNewlineAfter`
        $this->Undocumentable = self::get(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_CLOSE_TAG,
            \T_COMMA,
            \T_CONCAT,
            \T_DOUBLE_ARROW,
            \T_NULLSAFE_OBJECT_OPERATOR,
            \T_OBJECT_OPERATOR,
            \T_SEMICOLON,
            ...TG::OPERATOR_ARITHMETIC,
            ...TG::OPERATOR_ASSIGNMENT,
            ...TG::OPERATOR_BITWISE,
            ...TG::OPERATOR_COMPARISON,
            ...TG::OPERATOR_LOGICAL,
            ...TG::OPERATOR_TERNARY,
        );

        $this->Number = self::get(
            \T_LNUMBER,
            \T_DNUMBER,
        );

        $this->StringDelimiter = self::get(
            \T_DOUBLE_QUOTE,
            \T_START_HEREDOC,
            \T_END_HEREDOC,
            \T_BACKTICK,
        );

        $this->DoNotModify = self::get(
            \T_ENCAPSED_AND_WHITESPACE,
            \T_INLINE_HTML,
        );

        $this->DoNotModifyLeft = self::get(
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
            \T_END_HEREDOC,
        );

        $this->DoNotModifyRight = self::get(
            \T_CLOSE_TAG,
            \T_START_HEREDOC,
        );

        $this->Trim = self::merge(
            $this->DoNotModifyLeft,
            $this->DoNotModifyRight,
            self::get(
                \T_ATTRIBUTE_COMMENT,
                \T_WHITESPACE,
                ...TG::COMMENT,
            ),
        );

        $this->SuppressBlankBetween = self::get(
            \T_USE,
        );

        $this->SuppressBlankBetweenOneLine = self::get(
            \T_DECLARE,
        );

        $this->AddSpaceAround = self::get(
            \T_AS,
            \T_FUNCTION,
            \T_INSTEADOF,
            \T_USE,
        );

        $this->AddSpaceBefore = self::get(
            \T_ARRAY,
            \T_CALLABLE,
            \T_ELLIPSIS,
            \T_EXTENDS,
            \T_FN,
            \T_GLOBAL,
            \T_IMPLEMENTS,
            \T_NAME_FULLY_QUALIFIED,
            \T_NAME_QUALIFIED,
            \T_NAME_RELATIVE,
            \T_NS_SEPARATOR,
            \T_STATIC,
            \T_STRING,
            \T_VARIABLE,
            ...TG::DECLARATION_EXCEPT_MULTI_PURPOSE,
        );

        $this->AddSpaceAfter = self::get(
            \T_BREAK,
            \T_CASE,
            \T_CATCH,
            \T_CLONE,
            \T_CONTINUE,
            \T_ECHO,
            \T_ELSE,
            \T_ELSEIF,
            \T_FOR,
            \T_FOREACH,
            \T_GOTO,
            \T_IF,
            \T_INCLUDE,
            \T_INCLUDE_ONCE,
            \T_MATCH,
            \T_NEW,
            \T_PRINT,
            \T_REQUIRE,
            \T_REQUIRE_ONCE,
            \T_RETURN,
            \T_SWITCH,
            \T_THROW,
            \T_WHILE,
            \T_YIELD,
            \T_YIELD_FROM,
            ...TG::CAST,
        );

        $this->SuppressSpaceBefore = self::get(
            \T_NS_SEPARATOR,
        );

        $this->SuppressSpaceAfter = self::get(
            \T_DOUBLE_COLON,
            \T_ELLIPSIS,
            \T_NS_SEPARATOR,
            \T_NULLSAFE_OBJECT_OPERATOR,
            \T_OBJECT_OPERATOR,
        );

        $preserveBlankBefore = [
            \T_CLOSE_TAG,
        ];

        $preserveBlankAfter = [
            \T_CLOSE_BRACE,
            \T_COMMA,
            \T_COMMENT,
            \T_DOC_COMMENT,
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
            \T_SEMICOLON,
        ];

        $this->PreserveNewlineBefore = self::get(
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_COALESCE,
            \T_COALESCE_EQUAL,
            \T_CONCAT,
            \T_DOUBLE_ARROW,
            \T_LOGICAL_NOT,
            \T_NULLSAFE_OBJECT_OPERATOR,
            \T_OBJECT_OPERATOR,
            ...$preserveBlankBefore,
            ...TG::OPERATOR_ARITHMETIC,
            ...TG::OPERATOR_BITWISE,
            ...TG::OPERATOR_TERNARY,
        );

        $this->PreserveNewlineAfter = self::get(
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
            \T_COLON,
            \T_DOUBLE_ARROW,
            \T_EXTENDS,
            \T_IMPLEMENTS,
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_RETURN,
            \T_THROW,
            \T_YIELD,
            \T_YIELD_FROM,
            ...$preserveBlankAfter,
            ...TG::OPERATOR_ASSIGNMENT_EXCEPT_COALESCE,
            ...TG::OPERATOR_COMPARISON_EXCEPT_COALESCE,
            ...TG::OPERATOR_LOGICAL_EXCEPT_NOT,
        );

        $this->PreserveBlankBefore = self::get(
            ...$preserveBlankBefore,
        );

        $this->PreserveBlankAfter = self::get(
            ...$preserveBlankAfter,
        );

        $this->StatementTerminator = self::get(
            \T_COLON,
            \T_COMMA,
            \T_SEMICOLON,
        );

        $expressionDelimiter = [
            \T_DOUBLE_ARROW,
            ...TG::OPERATOR_ASSIGNMENT,
            ...TG::OPERATOR_COMPARISON_EXCEPT_COALESCE,
        ];

        $this->ExpressionTerminator = self::get(
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_SEMICOLON,
            ...$expressionDelimiter,
        );

        $this->ExpressionDelimiter = self::get(
            ...$expressionDelimiter,
        );

        $this->ExpressionDelimiterExceptComparison = self::get(
            \T_DOUBLE_ARROW,
            ...TG::OPERATOR_ASSIGNMENT,
        );

        $this->FunctionIdentifier = self::get(
            \T_STRING,
            ...TG::AMPERSAND,
        );

        $this->DereferenceableTerminator = self::get(
            ...TG::DEREFERENCEABLE_END,
        );

        $this->SwitchCaseDelimiter = self::get(
            \T_COLON,
            \T_SEMICOLON,
            \T_CLOSE_TAG,
        );

        $this->SwitchCaseOrDelimiter = self::get(
            \T_CASE,
            \T_DEFAULT,
            \T_COLON,
            \T_SEMICOLON,
            \T_CLOSE_TAG,
        );

        $this->ListParenthesisPredecessor = self::get(
            \T_ARRAY,
            \T_DECLARE,
            \T_FOR,
            \T_ISSET,
            \T_LIST,
            \T_STATIC,
            \T_UNSET,
            \T_USE,
            \T_VARIABLE,
            ...TG::MAYBE_ANONYMOUS,
            ...TG::DEREFERENCEABLE_SCALAR_END,
            ...TG::NAME,
        );

        $this->UnaryPredecessor = self::get(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_DOLLAR_OPEN_CURLY_BRACES,
            \T_AT,
            \T_BOOLEAN_AND,
            \T_BOOLEAN_OR,
            \T_COMMA,
            \T_CONCAT,
            \T_DOUBLE_ARROW,
            \T_ELLIPSIS,
            \T_SEMICOLON,
            ...TG::OPERATOR_ARITHMETIC,
            ...TG::OPERATOR_ASSIGNMENT,
            ...TG::OPERATOR_BITWISE,
            ...TG::OPERATOR_COMPARISON,
            ...TG::OPERATOR_LOGICAL,
            ...TG::CAST,
            ...TG::KEYWORD,
        );

        $this->AltSyntaxStart = self::get(...TG::ALT_SYNTAX_START);
        $this->AltSyntaxContinue = self::get(...TG::ALT_SYNTAX_CONTINUE);
        $this->AltSyntaxContinueWithExpression = self::get(...TG::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION);
        $this->AltSyntaxContinueWithoutExpression = self::get(...TG::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION);
        $this->AltSyntaxEnd = self::get(...TG::ALT_SYNTAX_END);
        $this->Ampersand = self::get(...TG::AMPERSAND);
        $this->ClassOrFunction = self::get(
            \T_CLASS,
            \T_FUNCTION,
        );
        $this->AfterAnonymousClassOrFunction = self::get(
            \T_OPEN_BRACE,
            \T_OPEN_PARENTHESIS,
            \T_EXTENDS,
            \T_IMPLEMENTS,
        );
        $this->CommaOrDoubleArrow = self::get(\T_COMMA, \T_DOUBLE_ARROW);
        $this->FunctionOrFn = self::get(\T_FN, \T_FUNCTION);
        $this->IfOrElseIf = self::get(\T_IF, \T_ELSEIF);
        $this->IfElseIfOrElse = self::get(\T_IF, \T_ELSEIF, \T_ELSE);
        $this->PlusOrMinus = self::get(\T_PLUS, \T_MINUS);
        $this->Attribute = self::get(
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
        );
        $this->Cast = self::get(...TG::CAST);
        $this->Chain = self::get(...TG::CHAIN);
        $this->ChainPart = self::get(...TG::CHAIN_PART);
        $this->ChainExpression = self::get(...TG::CHAIN_EXPRESSION);
        $this->Comment = self::get(...TG::COMMENT);
        $this->Declaration = self::get(...TG::DECLARATION);
        $this->DeclarationExceptModifiers = self::get(...TG::DECLARATION_EXCEPT_MODIFIERS);
        $this->DeclarationClass = self::get(...TG::DECLARATION_CLASS);
        $this->DeclarationClassOrFunction = self::get(
            \T_FUNCTION,
            ...TG::DECLARATION_CLASS,
        );
        $this->DeclarationList = self::get(...TG::DECLARATION_LIST);
        $this->DeclarationPropertyOrVariable = self::get(
            \T_STATIC,
            \T_VAR,
            ...TG::VISIBILITY_WITH_READONLY,
        );
        $this->DeclarationPart = self::get(...TG::DECLARATION_PART);
        $this->DeclarationPartWithNew = self::get(...TG::DECLARATION_PART_WITH_NEW);
        $this->DeclarationPartWithNewAndBody = self::get(
            \T_OPEN_BRACE,
            \T_CLOSE_BRACE,
            ...TG::DECLARATION_PART_WITH_NEW_AND_VALUE_TYPE,
        );
        $this->DeclarationTopLevel = self::get(...TG::DECLARATION_TOP_LEVEL);
        $this->HasStatement = self::get(...TG::HAS_STATEMENT);
        $this->HasStatementWithOptionalBraces = self::get(...TG::HAS_STATEMENT_WITH_OPTIONAL_BRACES);
        $this->HasExpressionAndStatementWithOptionalBraces = self::get(...TG::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES);
        $this->MaybeReserved = self::get(...TG::MAYBE_RESERVED);
        $this->NotCode = self::get(...TG::NOT_CODE);
        $this->Operator = self::get(...TG::OPERATOR_ALL);
        $this->OperatorAssignment = self::get(...TG::OPERATOR_ASSIGNMENT);
        $this->OperatorBooleanExceptNot = self::get(...TG::OPERATOR_BOOLEAN_EXCEPT_NOT);
        $this->OperatorComparison = self::get(...TG::OPERATOR_COMPARISON_EXCEPT_COALESCE);
        $this->OperatorUnary = self::get(
            \T_DOLLAR,
            \T_LOGICAL_NOT,
            \T_NOT,
            ...TG::OPERATOR_ERROR_CONTROL,
            ...TG::OPERATOR_INCREMENT_DECREMENT,
        );
        $this->Return = self::get(...TG::RETURN);
        $this->TypeDelimiter = self::get(...TG::TYPE_DELIMITER);
        $this->ValueType = self::get(...TG::VALUE_TYPE);
        $this->ValueTypeStart = self::get(...TG::VALUE_TYPE_START);
        $this->VariableEnd = self::get(...TG::VARIABLE_END);
        $this->Visibility = self::get(...TG::VISIBILITY);
        $this->VisibilityWithReadonly = self::get(...TG::VISIBILITY_WITH_READONLY);
        $this->Virtual = self::get(
            \T_END_ALT_SYNTAX,
            \T_NULL,
        );

        $this->LastOperators = self::MIXED;
        $this->_PreserveNewlineBefore = $this->PreserveNewlineBefore;
        $this->_PreserveNewlineAfter = $this->PreserveNewlineAfter;
    }

    /**
     * @return static
     */
    public function withLeadingOperators()
    {
        return (clone $this)->applyLeadingOperators();
    }

    /**
     * @return static
     */
    protected function applyLeadingOperators()
    {
        $both = self::intersect(
            $this->_PreserveNewlineBefore,
            $this->_PreserveNewlineAfter,
        );
        $preserveBefore = self::merge(
            $this->_PreserveNewlineBefore,
            self::get(
                ...TG::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL,
                ...TG::OPERATOR_COMPARISON,
                ...TG::OPERATOR_LOGICAL_EXCEPT_NOT,
            ),
        );
        $preserveAfter = self::merge(
            self::diff(
                $this->_PreserveNewlineAfter,
                $preserveBefore,
            ),
            $both
        );

        $this->PreserveNewlineBefore = $preserveBefore;
        $this->PreserveNewlineAfter = $preserveAfter;
        $this->LastOperators = self::LEADING;
        return $this;
    }

    /**
     * @return static
     */
    public function withTrailingOperators()
    {
        return (clone $this)->applyTrailingOperators();
    }

    /**
     * @return static
     */
    protected function applyTrailingOperators()
    {
        $both = self::intersect(
            $this->_PreserveNewlineBefore,
            $this->_PreserveNewlineAfter,
        );
        $preserveAfter = self::merge(
            $this->_PreserveNewlineAfter,
            self::get(
                \T_COALESCE,
                \T_COALESCE_EQUAL,
                \T_CONCAT,
                ...TG::OPERATOR_ARITHMETIC,
                ...TG::OPERATOR_BITWISE,
            ),
        );
        $preserveBefore = self::merge(
            self::diff(
                $this->_PreserveNewlineBefore,
                $preserveAfter,
            ),
            $both
        );

        $this->PreserveNewlineBefore = $preserveBefore;
        $this->PreserveNewlineAfter = $preserveAfter;
        $this->LastOperators = self::TRAILING;
        return $this;
    }

    /**
     * @return static
     */
    public function withMixedOperators()
    {
        return (clone $this)->applyMixedOperators();
    }

    /**
     * @return static
     */
    protected function applyMixedOperators()
    {
        $this->PreserveNewlineBefore = $this->_PreserveNewlineBefore;
        $this->PreserveNewlineAfter = $this->_PreserveNewlineAfter;
        $this->LastOperators = self::MIXED;
        return $this;
    }

    /**
     * @return static
     */
    public function withPreserveNewline()
    {
        switch ($this->LastOperators) {
            case self::LEADING:
                return $this->withLeadingOperators();
            case self::TRAILING:
                return $this->withTrailingOperators();
            case self::MIXED:
                return $this->withMixedOperators();
        }
    }

    /**
     * @return static
     */
    public function withoutPreserveNewline()
    {
        return $this->with('PreserveNewlineBefore', $this->PreserveBlankBefore)
                    ->with('PreserveNewlineAfter', $this->PreserveBlankAfter);
    }

    /**
     * Get an index of the given token types
     *
     * @return array<int,bool>
     */
    public static function get(int ...$types): array
    {
        return array_fill_keys($types, true) + self::TOKEN_INDEX;
    }

    /**
     * Get an index of every token type in the given indexes
     *
     * @param array<int,bool> ...$indexes
     * @return array<int,bool>
     */
    public static function merge(array ...$indexes): array
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
    public static function diff(array $index, array ...$indexes): array
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
    public static function intersect(array $index, array ...$indexes): array
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
