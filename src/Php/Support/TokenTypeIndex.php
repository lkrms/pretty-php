<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Support;

use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IImmutable;
use Lkrms\Pretty\Php\Catalog\TokenType as TT;

/**
 * Indexed tokens by type
 *
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

        $this->AltSyntaxContinue = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE);
        $this->AltSyntaxEnd = TT::getIndex(...TT::ALT_SYNTAX_END);
        $this->NotCode = TT::getIndex(...TT::NOT_CODE);
    }
}
