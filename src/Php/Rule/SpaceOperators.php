<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class SpaceOperators implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->isOperator() || $token->isUnaryOperator() || $token->parent()->prev()->is(T_DECLARE))
        {
            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
        $token->WhitespaceAfter  |= WhitespaceType::SPACE;
    }
}
