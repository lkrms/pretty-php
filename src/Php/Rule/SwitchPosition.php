<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class SwitchPosition implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->isOneOf(T_CASE, T_SWITCH))
        {
            return;
        }

        if ($token->is(T_SWITCH))
        {
            $token->nextSibling(2)->inner()->withEach(fn(Token $t) => $t->Indent++);

            return;
        }

        if (!($separator = $token->nextSiblingOf(":", ";")))
        {
            return;
        }

        $token->WhitespaceBefore    |= WhitespaceType::BLANK;
        $separator->WhitespaceAfter |= WhitespaceType::LINE;
        $token->collect($separator)->withEach(fn(Token $t) => $t->Deindent++);

    }
}
