<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class SwitchPosition implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->is(T_SWITCH)) {
            $token->nextSibling(2)->inner()->forEach(fn(Token $t) => $t->Indent++);

            return;
        }

        if (!$token->isOneOf(T_CASE, T_DEFAULT) || !$token->parent()->prevSibling(2)->is(T_SWITCH)) {
            return;
        }

        if (!($separator = $token->nextSiblingOf(':', ';', T_CLOSE_TAG))) {
            return;
        }

        $token->WhitespaceBefore       |= WhitespaceType::LINE;
        $separator->WhitespaceAfter    |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $separator->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        $token->collect($separator)->forEach(fn(Token $t) => $t->Deindent++);
    }
}
