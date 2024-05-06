<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\PrettyPHP\Catalog\TokenType as TT;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasImmutableProperties;

/**
 * Indexed tokens by type
 *
 * @api
 */
class TokenTypeIndex implements Immutable
{
    use HasImmutableProperties {
        withPropertyValue as with;
    }

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
     * T_OPEN_BRACKET, T_OPEN_PARENTHESIS, T_ATTRIBUTE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBracketExceptBrace;

    /**
     * T_CLOSE_BRACKET, T_CLOSE_PARENTHESIS
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracketExceptBrace;

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
     * T_ABSTRACT, T_FINAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY,
     * T_STATIC, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $VarOrModifier;

    /**
     * Tokens that may contain tab characters
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Expandable;

    /**
     * Tokens that may be swapped with adjacent comment tokens when operator
     * position is changed
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Movable;

    /**
     * Tokens where a preceding DocBlock can be demoted to a standard C-style
     * comment without loss of information
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
     * T_END_HEREDOC, T_COMMENT, T_DOC_COMMENT, T_WHITESPACE
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Trim;

    /**
     * Tokens that require leading and trailing spaces
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAround;

    /**
     * Tokens that require leading spaces
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceBefore;

    /**
     * Tokens that require trailing spaces
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAfter;

    /**
     * Tokens that require suppression of leading spaces
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressSpaceBefore;

    /**
     * Tokens that require suppression of trailing spaces
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressSpaceAfter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveNewlineBefore;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveNewlineAfter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveBlankBefore;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $PreserveBlankAfter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $ExpressionTerminator;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $ExpressionDelimiter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $FunctionIdentifier;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $DereferenceableTerminator;

    /**
     * T_CASE, T_DEFAULT, T_COLON, T_SEMICOLON, T_CLOSE_TAG
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $SwitchCaseOrDelimiter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $UnaryPredecessor;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $T_DO;

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
     * T_ATTRIBUTE, T_ATTRIBUTE_COMMENT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Attribute;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $Chain;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $ChainPart;

    /**
     * T_COMMENT, T_DOC_COMMENT
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Comment;

    /**
     * T_ABSTRACT, T_CASE, T_CLASS, T_CONST, T_DECLARE, T_ENUM, T_FINAL,
     * T_FUNCTION, T_GLOBAL, T_INTERFACE, T_NAMESPACE, T_PRIVATE, T_PROTECTED,
     * T_PUBLIC, T_READONLY, T_STATIC, T_TRAIT, T_USE, T_VAR
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
     * T_GLOBAL, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPropertyOrVariable;

    /**
     * T_ABSTRACT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ATTRIBUTE_COMMENT,
     * T_ATTRIBUTE, T_CASE, T_CLASS, T_COMMA, T_CONST, T_DECLARE, T_ENUM,
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_GLOBAL, T_IMPLEMENTS, T_INTERFACE,
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
     * T_EXTENDS, T_FINAL, T_FUNCTION, T_GLOBAL, T_IMPLEMENTS, T_INTERFACE,
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
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AND, T_ARRAY,
     * T_ATTRIBUTE_COMMENT, T_ATTRIBUTE, T_CALLABLE, T_CASE, T_CLASS,
     * T_CLOSE_BRACE, T_CLOSE_PARENTHESIS, T_COLON, T_COMMA, T_CONST, T_DECLARE,
     * T_ENUM, T_EXTENDS, T_FINAL, T_FUNCTION, T_GLOBAL, T_IMPLEMENTS,
     * T_INTERFACE, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE,
     * T_NAMESPACE, T_NEW, T_NS_SEPARATOR, T_OPEN_BRACE, T_OPEN_PARENTHESIS,
     * T_OR, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_STATIC, T_STRING,
     * T_TRAIT, T_USE, T_VAR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $DeclarationPartWithNewAndBody;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $HasStatement;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $HasStatementWithOptionalBraces;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $HasExpressionAndStatementWithOptionalBraces;

    /**
     * T_STRING, TokenType::SEMI_RESERVED
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $MaybeReserved;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $NotCode;

    /**
     * T_AND, T_OR, T_XOR, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND,
     * T_LOGICAL_OR, T_LOGICAL_XOR
     *
     * `&`, `|`, `^`, `&&`, `||`, `and`, `or`, `xor`
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $OperatorBooleanExceptNot;

    /**
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_OR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $TypeDelimiter;

    /**
     * T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAMESPACE,
     * T_NS_SEPARATOR, T_STRING, T_OPEN_PARENTHESIS, T_CLOSE_PARENTHESIS,
     * T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_OR
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $ValueType;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $Visibility;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $VisibilityWithReadonly;

    private string $LastOperatorsMethod;

    /**
     * @var array<int,bool>
     */
    private array $_PreserveNewlineBefore;

    /**
     * @var array<int,bool>
     */
    private array $_PreserveNewlineAfter;

    public function __construct()
    {
        $this->Bracket = TT::getIndex(
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

        $this->OpenBracket = TT::getIndex(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_ATTRIBUTE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->CloseBracket = TT::getIndex(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
        );

        $this->CloseBracketOrEndAltSyntax = TT::getIndex(
            \T_CLOSE_BRACE,
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_END_ALT_SYNTAX,
        );

        $this->OpenBracketExceptBrace = TT::getIndex(
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_ATTRIBUTE,
        );

        $this->CloseBracketExceptBrace = TT::getIndex(
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
        );

        $this->OpenBrace = TT::getIndex(
            \T_OPEN_BRACE,
            \T_CURLY_OPEN,
            \T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->OpenTag = TT::getIndex(
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
        );

        $this->ContinuesControlStructure = TT::getIndex(
            \T_ELSEIF,
            \T_ELSE,
            \T_CATCH,
            \T_FINALLY,
        );

        $this->VarOrModifier = TT::getIndex(
            \T_VAR,
            ...TT::KEYWORD_MODIFIER,
        );

        $this->Expandable = TT::getIndex(
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

        $this->Movable = TT::getIndex(
            \T_CONCAT,
            ...TT::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL,
            ...TT::OPERATOR_COMPARISON,
            ...TT::OPERATOR_LOGICAL_EXCEPT_NOT,
            ...TT::OPERATOR_ARITHMETIC,
            ...TT::OPERATOR_BITWISE,
        );

        // Derived from operators in `$this->PreserveNewlineBefore` and
        // `$this->PreserveNewlineAfter`
        $this->Undocumentable = TT::getIndex(
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
            ...TT::OPERATOR_ARITHMETIC,
            ...TT::OPERATOR_ASSIGNMENT,
            ...TT::OPERATOR_BITWISE,
            ...TT::OPERATOR_COMPARISON,
            ...TT::OPERATOR_LOGICAL,
            ...TT::OPERATOR_TERNARY,
        );

        $this->Number = TT::getIndex(
            \T_LNUMBER,
            \T_DNUMBER,
        );

        $this->StringDelimiter = TT::getIndex(
            \T_DOUBLE_QUOTE,
            \T_START_HEREDOC,
            \T_END_HEREDOC,
            \T_BACKTICK,
        );

        $this->DoNotModify = TT::getIndex(
            \T_ENCAPSED_AND_WHITESPACE,
            \T_INLINE_HTML,
        );

        $this->DoNotModifyLeft = TT::getIndex(
            \T_OPEN_TAG,
            \T_OPEN_TAG_WITH_ECHO,
            \T_END_HEREDOC,
        );

        $this->DoNotModifyRight = TT::getIndex(
            \T_CLOSE_TAG,
            \T_START_HEREDOC,
        );

        $this->Trim = TT::mergeIndexes(
            $this->DoNotModifyLeft,
            $this->DoNotModifyRight,
            TT::getIndex(
                \T_ATTRIBUTE_COMMENT,
                \T_WHITESPACE,
                ...TT::COMMENT,
            ),
        );

        $this->AddSpaceAround = TT::getIndex(
            \T_AS,
            \T_FUNCTION,
            \T_INSTEADOF,
            \T_USE,
        );

        $this->AddSpaceBefore = TT::getIndex(
            \T_ARRAY,
            \T_CALLABLE,
            \T_ELLIPSIS,
            \T_EXTENDS,
            \T_FN,
            \T_IMPLEMENTS,
            \T_NAME_FULLY_QUALIFIED,
            \T_NAME_QUALIFIED,
            \T_NAME_RELATIVE,
            \T_NS_SEPARATOR,
            \T_STATIC,
            \T_STRING,
            \T_VARIABLE,
            ...TT::DECLARATION_EXCEPT_MULTI_PURPOSE,
        );

        $this->AddSpaceAfter = TT::getIndex(
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
            ...TT::CAST,
        );

        $this->SuppressSpaceBefore = TT::getIndex(
            \T_NS_SEPARATOR,
        );

        $this->SuppressSpaceAfter = TT::getIndex(
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

        $this->PreserveNewlineBefore = TT::getIndex(
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
            ...TT::OPERATOR_ARITHMETIC,
            ...TT::OPERATOR_BITWISE,
            ...TT::OPERATOR_TERNARY,
        );

        $this->PreserveNewlineAfter = TT::getIndex(
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
            ...TT::OPERATOR_ASSIGNMENT_EXCEPT_COALESCE,
            ...TT::OPERATOR_COMPARISON_EXCEPT_COALESCE,
            ...TT::OPERATOR_LOGICAL_EXCEPT_NOT,
        );

        $this->PreserveBlankBefore = TT::getIndex(
            ...$preserveBlankBefore,
        );

        $this->PreserveBlankAfter = TT::getIndex(
            ...$preserveBlankAfter,
        );

        $expressionDelimiter = [
            \T_DOUBLE_ARROW,
            ...TT::OPERATOR_ASSIGNMENT,
            ...TT::OPERATOR_COMPARISON_EXCEPT_COALESCE,
        ];

        $this->ExpressionTerminator = TT::getIndex(
            \T_CLOSE_BRACKET,
            \T_CLOSE_PARENTHESIS,
            \T_SEMICOLON,
            ...$expressionDelimiter,
        );

        $this->ExpressionDelimiter = TT::getIndex(
            ...$expressionDelimiter,
        );

        $this->FunctionIdentifier = TT::getIndex(
            \T_STRING,
            \T_READONLY,
            ...TT::AMPERSAND,
        );

        $this->DereferenceableTerminator = TT::getIndex(
            ...TT::DEREFERENCEABLE_END,
        );

        $this->SwitchCaseOrDelimiter = TT::getIndex(
            \T_CASE,
            \T_DEFAULT,
            \T_COLON,
            \T_SEMICOLON,
            \T_CLOSE_TAG,
        );

        $this->UnaryPredecessor = TT::getIndex(
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
            ...TT::OPERATOR_ARITHMETIC,
            ...TT::OPERATOR_ASSIGNMENT,
            ...TT::OPERATOR_BITWISE,
            ...TT::OPERATOR_COMPARISON,
            ...TT::OPERATOR_LOGICAL,
            ...TT::CAST,
            ...TT::KEYWORD,
        );

        $this->T_DO = TT::getIndex(\T_DO);

        $this->AltSyntaxStart = TT::getIndex(...TT::ALT_SYNTAX_START);
        $this->AltSyntaxContinue = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE);
        $this->AltSyntaxContinueWithExpression = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE_WITH_EXPRESSION);
        $this->AltSyntaxContinueWithoutExpression = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE_WITHOUT_EXPRESSION);
        $this->AltSyntaxEnd = TT::getIndex(...TT::ALT_SYNTAX_END);
        $this->Ampersand = TT::getIndex(...TT::AMPERSAND);
        $this->Attribute = TT::getIndex(
            \T_ATTRIBUTE,
            \T_ATTRIBUTE_COMMENT,
        );
        $this->Chain = TT::getIndex(...TT::CHAIN);
        $this->ChainPart = TT::getIndex(...TT::CHAIN_PART);
        $this->Comment = TT::getIndex(...TT::COMMENT);
        $this->Declaration = TT::getIndex(...TT::DECLARATION);
        $this->DeclarationExceptModifiers = TT::getIndex(...TT::DECLARATION_EXCEPT_MODIFIERS);
        $this->DeclarationClass = TT::getIndex(...TT::DECLARATION_CLASS);
        $this->DeclarationPropertyOrVariable = TT::getIndex(
            \T_GLOBAL,
            \T_STATIC,
            \T_VAR,
            ...TT::VISIBILITY_WITH_READONLY,
        );
        $this->DeclarationPart = TT::getIndex(...TT::DECLARATION_PART);
        $this->DeclarationPartWithNew = TT::getIndex(...TT::DECLARATION_PART_WITH_NEW);
        $this->DeclarationPartWithNewAndBody = TT::getIndex(
            \T_OPEN_BRACE,
            \T_CLOSE_BRACE,
            ...TT::DECLARATION_PART_WITH_NEW_AND_VALUE_TYPE,
        );
        $this->HasStatement = TT::getIndex(...TT::HAS_STATEMENT);
        $this->HasStatementWithOptionalBraces = TT::getIndex(...TT::HAS_STATEMENT_WITH_OPTIONAL_BRACES);
        $this->HasExpressionAndStatementWithOptionalBraces = TT::getIndex(...TT::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES);
        $this->MaybeReserved = TT::getIndex(\T_STRING, ...TT::SEMI_RESERVED);
        $this->NotCode = TT::getIndex(...TT::NOT_CODE);
        $this->OperatorBooleanExceptNot = TT::getIndex(...TT::OPERATOR_BOOLEAN_EXCEPT_NOT);
        $this->TypeDelimiter = TT::getIndex(...TT::TYPE_DELIMITER);
        $this->ValueType = TT::getIndex(...TT::VALUE_TYPE);
        $this->Visibility = TT::getIndex(...TT::VISIBILITY);
        $this->VisibilityWithReadonly = TT::getIndex(...TT::VISIBILITY_WITH_READONLY);

        $this->LastOperatorsMethod = 'withMixedOperators';
        $this->_PreserveNewlineBefore = $this->PreserveNewlineBefore;
        $this->_PreserveNewlineAfter = $this->PreserveNewlineAfter;
    }

    /**
     * @return static
     */
    public function withLeadingOperators()
    {
        $both = TT::intersectIndexes(
            $this->_PreserveNewlineBefore,
            $this->_PreserveNewlineAfter,
        );
        $preserveBefore = TT::mergeIndexes(
            $this->_PreserveNewlineBefore,
            TT::getIndex(
                ...TT::OPERATOR_ASSIGNMENT_EXCEPT_EQUAL,
                ...TT::OPERATOR_COMPARISON,
                ...TT::OPERATOR_LOGICAL_EXCEPT_NOT,
            ),
        );
        $preserveAfter = TT::mergeIndexes(
            TT::diffIndexes(
                $this->_PreserveNewlineAfter,
                $preserveBefore,
            ),
            $both
        );

        return $this->with('PreserveNewlineBefore', $preserveBefore)
                    ->with('PreserveNewlineAfter', $preserveAfter)
                    ->with('LastOperatorsMethod', __FUNCTION__);
    }

    /**
     * @return static
     */
    public function withTrailingOperators()
    {
        $both = TT::intersectIndexes(
            $this->_PreserveNewlineBefore,
            $this->_PreserveNewlineAfter,
        );
        $preserveAfter = TT::mergeIndexes(
            $this->_PreserveNewlineAfter,
            TT::getIndex(
                \T_COALESCE,
                \T_COALESCE_EQUAL,
                \T_CONCAT,
                ...TT::OPERATOR_ARITHMETIC,
                ...TT::OPERATOR_BITWISE,
            ),
        );
        $preserveBefore = TT::mergeIndexes(
            TT::diffIndexes(
                $this->_PreserveNewlineBefore,
                $preserveAfter,
            ),
            $both
        );

        return $this->with('PreserveNewlineBefore', $preserveBefore)
                    ->with('PreserveNewlineAfter', $preserveAfter)
                    ->with('LastOperatorsMethod', __FUNCTION__);
    }

    /**
     * @return static
     */
    public function withMixedOperators()
    {
        return $this->with('PreserveNewlineBefore', $this->_PreserveNewlineBefore)
                    ->with('PreserveNewlineAfter', $this->_PreserveNewlineAfter)
                    ->with('LastOperatorsMethod', __FUNCTION__);
    }

    /**
     * @return static
     */
    public function withPreserveNewline()
    {
        return $this->{$this->LastOperatorsMethod}();
    }

    /**
     * @return static
     */
    public function withoutPreserveNewline()
    {
        return $this->with('PreserveNewlineBefore', $this->PreserveBlankBefore)
                    ->with('PreserveNewlineAfter', $this->PreserveBlankAfter);
    }

    public static function create(): TokenTypeIndex
    {
        return new self();
    }
}
