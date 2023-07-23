<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Support;

use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IImmutable;
use Lkrms\Pretty\Php\Catalog\TokenType as TT;

/**
 * Indexed tokens by type
 *
 * @api
 */
final class TokenTypeIndex implements IImmutable
{
    use HasMutator {
        withPropertyValue as public with;
    }

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $Bracket;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $StandardBracket;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $OpenBracket;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracket;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $StandardOpenBracket;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $CloseBracketOrEndAltSyntax;

    /**
     * Tokens that may contain tab characters
     *
     * @readonly
     * @var array<int,bool>
     */
    public array $Expandable;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModify;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModifyLeft;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $DoNotModifyRight;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAround;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceBefore;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $AddSpaceAfter;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $SuppressSpaceBefore;

    /**
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
    public array $AltSyntaxContinue;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $AltSyntaxEnd;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $NotCode;

    public function __construct()
    {
        $this->Bracket = TT::getIndex(
            T_OPEN_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
            T_CLOSE_BRACE,
            T_CLOSE_BRACKET,
            T_CLOSE_PARENTHESIS,
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->StandardBracket = TT::getIndex(
            T_OPEN_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
            T_CLOSE_BRACE,
            T_CLOSE_BRACKET,
            T_CLOSE_PARENTHESIS,
        );

        $this->OpenBracket = TT::getIndex(
            T_OPEN_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
            T_ATTRIBUTE,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
        );

        $this->CloseBracket = TT::getIndex(
            T_CLOSE_BRACE,
            T_CLOSE_BRACKET,
            T_CLOSE_PARENTHESIS,
        );

        $this->StandardOpenBracket = TT::getIndex(
            T_OPEN_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
        );

        $this->CloseBracketOrEndAltSyntax = TT::getIndex(
            T_CLOSE_BRACE,
            T_CLOSE_BRACKET,
            T_CLOSE_PARENTHESIS,
            T_END_ALT_SYNTAX,
        );

        $this->Expandable = TT::getIndex(
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_COMMENT,
            T_DOC_COMMENT,
            T_ATTRIBUTE_COMMENT,
            T_CONSTANT_ENCAPSED_STRING,
            T_ENCAPSED_AND_WHITESPACE,
            T_START_HEREDOC,
            T_END_HEREDOC,
            T_INLINE_HTML,
            T_WHITESPACE,
        );

        $this->DoNotModify = TT::getIndex(
            T_ENCAPSED_AND_WHITESPACE,
            T_INLINE_HTML,
        );

        $this->DoNotModifyLeft = TT::getIndex(
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_END_HEREDOC,
        );

        $this->DoNotModifyRight = TT::getIndex(
            T_CLOSE_TAG,
            T_START_HEREDOC,
        );

        $this->AddSpaceAround = TT::getIndex(
            T_AS,
            T_FUNCTION,
            T_INSTEADOF,
            T_USE,
        );

        $this->AddSpaceBefore = TT::getIndex(
            T_ARRAY,
            T_CALLABLE,
            T_ELLIPSIS,
            T_FN,
            T_NAME_FULLY_QUALIFIED,
            T_NAME_QUALIFIED,
            T_NAME_RELATIVE,
            T_NS_SEPARATOR,
            T_STATIC,
            T_STRING,
            T_VARIABLE,
            ...TT::DECLARATION_EXCEPT_MULTI_PURPOSE,
        );

        $this->AddSpaceAfter = TT::getIndex(
            T_BREAK,
            T_CASE,
            T_CATCH,
            T_CLONE,
            T_CONTINUE,
            T_ECHO,
            T_ELSE,
            T_ELSEIF,
            T_EXIT,
            T_FOR,
            T_FOREACH,
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
            ...TT::CAST,
        );

        $this->SuppressSpaceBefore = TT::getIndex(
            T_NS_SEPARATOR,
        );

        $this->SuppressSpaceAfter = TT::getIndex(
            T_DOUBLE_COLON,
            T_ELLIPSIS,
            T_NS_SEPARATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_OBJECT_OPERATOR,
        );

        $preserveBlankBefore = [
            T_CLOSE_TAG,
        ];

        $preserveBlankAfter = [
            T_CLOSE_BRACE,
            T_COMMA,
            T_COMMENT,
            T_DOC_COMMENT,
            T_OPEN_TAG,
            T_OPEN_TAG_WITH_ECHO,
            T_SEMICOLON,
        ];

        $this->PreserveNewlineBefore = TT::getIndex(
            T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
            T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
            T_CLOSE_BRACKET,
            T_CLOSE_PARENTHESIS,
            T_COALESCE,
            T_CONCAT,
            T_DOUBLE_ARROW,
            T_LOGICAL_NOT,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_OBJECT_OPERATOR,
            ...$preserveBlankBefore,
            ...TT::OPERATOR_ARITHMETIC,
            ...TT::OPERATOR_BITWISE,
            ...TT::OPERATOR_TERNARY,
        );

        $this->PreserveNewlineAfter = TT::getIndex(
            T_COLON,
            T_DOUBLE_ARROW,
            T_EXTENDS,
            T_IMPLEMENTS,
            T_OPEN_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
            T_RETURN,
            T_YIELD,
            T_YIELD_FROM,
            ...$preserveBlankAfter,
            ...TT::OPERATOR_ASSIGNMENT,
            ...TT::OPERATOR_COMPARISON_EXCEPT_COALESCE,
            ...TT::OPERATOR_LOGICAL_EXCEPT_NOT,
        );

        $this->PreserveBlankBefore = TT::getIndex(
            ...$preserveBlankBefore,
        );

        $this->PreserveBlankAfter = TT::getIndex(
            ...$preserveBlankAfter,
        );

        $this->AltSyntaxContinue = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE);
        $this->AltSyntaxEnd = TT::getIndex(...TT::ALT_SYNTAX_END);
        $this->NotCode = TT::getIndex(...TT::NOT_CODE);
    }
}
