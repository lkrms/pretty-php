<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeYield extends AbstractTokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->isOneOf(T_YIELD, T_YIELD_FROM) &&
                !($token->prev()->isOneOf(...TokenType::COMMENT) && $token->prev()->hasNewlineBefore())) {
            $token->WhitespaceBefore |= WhitespaceType::BLANK;
        }
    }
}
