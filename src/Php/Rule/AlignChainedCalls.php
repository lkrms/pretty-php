<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

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
                       ->filter(fn(Token $t) =>
                           $t->isOneOf(T_OBJECT_OPERATOR,
                                       T_NULLSAFE_OBJECT_OPERATOR));

        // Don't process tokens in the chain multiple times
        $chain->forEach(fn(Token $t) => $t->ChainOpenedBy = $token);

        /** @var ?Token $alignWith */
        $alignWith = null;
        // Find the first `->` in the chain with a leading newline
        $first     = $chain->find(
            function (Token $token, ?Token $prev) use (&$alignWith): bool {
                if (!$token->hasNewlineBefore()) {
                    return false;
                }
                $alignWith = $prev;

                return true;
            }
        );

        // If there are no leading newlines, there's nothing to do
        if (!$first) {
            return;
        }

        // If the first `->` in the chain has a leading newline ($alignWith
        // would be set otherwise), align with the closest `::` in the same
        // expression (if there is one)
        if (!$alignWith) {
            $alignWith = $first->prevSiblingsWhile('(', '[', '{',
                                                   T_DOUBLE_COLON,
                                                   T_STRING)
                               ->getFirstOf(T_DOUBLE_COLON);
        }

        // Remove tokens before $first from the chain
        while ($chain->first()->Index < $first->Index) {
            $chain->shift();
        }

        // Apply a leading newline to the remaining tokens
        $chain->forEach(fn(Token $t) =>
            $t === $first || ($t->WhitespaceBefore |= WhitespaceType::LINE));
        $this->Formatter->registerCallback($this,
                                           $alignWith ?: $first,
                                           fn() => $this->alignChain($chain, $first, $alignWith),
                                           710);
    }

    private function alignChain(TokenCollection $chain, Token $first, ?Token $alignWith): void
    {
        // Don't proceed if tokens have been moved to the previous line by other
        // rules
        if ($chain->find(fn(Token $t) => !$t->hasNewlineBefore())) {
            return;
        }

        // Create a hanging indent if there's nothing to $alignWith
        if (!$alignWith) {
            $delta = $this->Formatter->TabSize;
        } else {
            $delta = max(0, mb_strlen(ltrim($alignWith->startOfLine()->collect($alignWith)->render(true, false), "\n"))
                - mb_strlen(ltrim($first->startOfLine()->collect($first)->render(true, false), "\n")));

            // If aligning with `::` and there's a long string before it, align
            // just inside the start of the string instead
            if ($alignWith->is(T_DOUBLE_COLON) &&
                    ($prev = $alignWith->prev())->is(T_STRING) &&
                    ($length = mb_strlen($prev->Code)) > 2 * $this->Formatter->TabSize - 1) {
                $delta -= $length - $this->Formatter->TabSize;
            }
        }

        // Apply the same delta to code between $alignWith and $first
        if ($alignWith) {
            $first = $alignWith->next();
        }

        $first->collect($first->endOfExpression())
              ->filter(fn(Token $t) => $t->hasNewlineBefore())
              ->forEach(fn(Token $t) => $t->Padding += $delta);
    }
}
