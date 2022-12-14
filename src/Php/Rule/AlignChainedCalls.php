<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

class AlignChainedCalls implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->ChainOpenedBy ||
                !$token->isOneOf(T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR)) {
            return;
        }
        $chain = $token->withNextSiblingsWhile('(', '[', '{',
                                               T_OBJECT_OPERATOR,
                                               T_NULLSAFE_OBJECT_OPERATOR,
                                               T_STRING)
                       ->filter(fn(Token $t) => $t->isOneOf(T_OBJECT_OPERATOR,
                                                            T_NULLSAFE_OBJECT_OPERATOR));
        if ($token->hasNewlineBefore() || count($chain) > 1) {
            $chain->forEach(fn(Token $t) => $t === $token || ($t->ChainOpenedBy = $token));
            $this->Formatter->registerCallback($this, $chain->first(), fn() => $this->alignChain($chain));
        }
    }

    private function alignChain(TokenCollection $chain): void
    {
        // Start from the last `->` with no preceding newline, if one exists
        do {
            /** @var Token $token */
            $token = $chain->shift();
            if ($token->hasNewlineBefore()) {
                break;
            }
            /** @var Token|false $next */
            $next = $chain->first();
        } while ($next && !$next->hasNewlineBefore());
        // Reduce the remaining chain to object operators immediately after
        // newlines
        $chain = $chain->filter(fn(Token $t) => $t->hasNewlineBefore());
        if (!($token->hasNewlineBefore() || count($chain))) {
            return;
        }
        // If the first `->` is immediately after a newline, align with the
        // closest `::` in the same expression if one exists, otherwise
        // indent the `->`
        if ($token->hasNewlineBefore()) {
            $alignWith = $token->prevSiblingsWhile('(', '[', '{',
                                                   T_DOUBLE_COLON,
                                                   T_STRING)
                               ->getFirstOf(T_DOUBLE_COLON);
            if ($alignWith && ($length = mb_strlen(trim($alignWith->prevCode()->outer()->render(true)))) > 7) {
                $adjust = 4 - $length;
            }
        } else {
            $alignWith = $token;
        }
        if (!$alignWith) {
            $alignWith = $token;
            $adjust    = 4;
        }
        $start   = $alignWith->startOfLine();
        $padding = max(0, mb_strlen($start->collect($alignWith)->render(true))
            - (mb_strlen($alignWith->Code) + strlen($alignWith->renderIndent(true)) + $start->Padding)
            + ($adjust ?? 0));
        $token->collect($token->endOfExpression())
              ->filter(fn(Token $t) => $t->hasNewlineBefore())
              ->forEach(fn(Token $t) => $t->Padding += $padding);
    }
}
