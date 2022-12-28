<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeYield implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isOneOf(T_YIELD, T_YIELD_FROM) &&
            !$token->prevStatementStart()->isOneOf(T_RETURN, T_YIELD, T_YIELD_FROM) &&
            !($token->prev()->isOneOf(...TokenType::COMMENT) &&
                $token->prev()->hasNewlineBefore())) {
            $token->WhitespaceBefore |= WhitespaceType::BLANK;
        }
    }
}
