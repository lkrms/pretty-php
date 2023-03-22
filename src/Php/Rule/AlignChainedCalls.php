<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

/**
 * Align consecutive object operators with the first in a chain of calls
 *
 */
final class AlignChainedCalls implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 340;
    }

    public function getTokenTypes(): ?array
    {
        return TokenType::CHAIN;
    }

    public function processToken(Token $token): void
    {
        if ($token !== $token->ChainOpenedBy) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...TokenType::CHAIN_PART)
                       ->filter(fn(Token $t) => $t->is(TokenType::CHAIN));

        /** @var ?Token $alignWith */
        $alignWith = null;

        // Find the $first `->` with a leading newline in the chain and assign
        // its predecessor (if any) to $alignWith
        $first = $chain->find(
            function (Token $token, ?Token $next, ?Token $prev) use (&$alignWith): bool {
                if (!$token->hasNewlineBefore()) {
                    return false;
                }
                $alignWith = $prev;

                return true;
            }
        );

        // If there's no `->` in the chain with a leading newline, do nothing
        if (!$first) {
            return;
        }

        // If the first `->` in the chain has a leading newline ($alignWith
        // would be set otherwise), align with the start of the chain
        if ($alignWith) {
            $alignWith->AlignedWith = $alignWith;
            $adjust                 = 0;
        } else {
            if (!($alignWith = $first->prevSiblingsWhile(
                T_DOUBLE_COLON,
                T_NAME_FULLY_QUALIFIED,
                T_NAME_QUALIFIED,
                T_NAME_RELATIVE,
                T_VARIABLE,
                ...TokenType::CHAIN_PART
            )->last())) {
                return;
            }
            $adjust = $this->Formatter->TabSize + 2 - mb_strlen($alignWith->text);
        }

        // Remove tokens before $first from the chain
        while ($chain->first()->Index < $first->Index) {
            $chain->shift();
        }

        // Apply a leading newline to the remaining tokens
        $chain->forEach(
            function (Token $t, ?Token $next, ?Token $prev) use ($alignWith) {
                if ($prev) {
                    $t->WhitespaceBefore |= WhitespaceType::LINE;
                }
                $t->AlignedWith = $alignWith;
            }
        );

        $this->Formatter->registerCallback(
            $this,
            $alignWith,
            fn() => $this->alignChain($chain, $first, $alignWith, $adjust),
            710
        );
    }

    private function alignChain(TokenCollection $chain, Token $first, Token $alignWith, int $adjust): void
    {
        // Don't proceed if operators have been moved by other rules
        if ($chain->find(fn(Token $t) => !$t->hasNewlineBefore())) {
            return;
        }

        $length = mb_strlen($alignWith->text);
        $delta  = $alignWith->alignmentOffset() - $length + $adjust;
        $callback =
            function (Token $t, ?Token $next) use ($length, $delta) {
                $t->collect($next ? $next->prev() : $t->pragmaticEndOfExpression())
                  ->forEach(
                      function (Token $_t) use ($length, $delta, $t) {
                          $_t->LinePadding += $delta;
                          if ($_t === $t) {
                              $_t->LineUnpadding += mb_strlen($_t->text) - $length;
                          }
                      }
                  );
            };
        // Apply $delta to code between $alignWith and $first
        $callback($alignWith->next(), $first);
        $chain->forEach($callback);
    }
}
