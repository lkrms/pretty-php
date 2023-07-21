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
    public array $AltSyntaxEndOrContinue;

    /**
     * @readonly
     * @var array<int,bool>
     */
    public array $NotCode;

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

        $this->AltSyntaxEndOrContinue = TT::getIndex(...TT::ALT_SYNTAX_CONTINUE, ...TT::ALT_SYNTAX_END);
        $this->NotCode = TT::getIndex(...TT::NOT_CODE);
        $this->DoNotModify = TT::getIndex(...TT::DO_NOT_MODIFY);
        $this->DoNotModifyLeft = TT::getIndex(...TT::DO_NOT_MODIFY_LHS);
        $this->DoNotModifyRight = TT::getIndex(...TT::DO_NOT_MODIFY_RHS);
    }
}
