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
        if (!$token->isBinaryOrTernaryOperator() || $token->parent()->prev()->is(T_DECLARE))
        {
            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;

        // Collapse ternary operators if there is nothing between `?` and `:`
        if ($token->isTernaryOperator() && $token->prev()->isTernaryOperator())
        {
            $token->WhitespaceBefore = $token->prev()->WhitespaceAfter = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
    }
}
