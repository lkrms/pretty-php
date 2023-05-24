<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

/**
 * Align consecutive object operators in the same chain of method calls
 *
 */
final class AlignChainedCalls implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 340;
    }

    public function getTokenTypes(): array
    {
        return TokenType::CHAIN;
    }

    public function processToken(Token $token): void
    {
        if ($token !== $token->ChainOpenedBy ||
            ($this->Formatter->OnlyAlignChainedStatements &&
                ($start = $token->pragmaticStartOfExpression()) !== $token->Statement &&
                !$start->prevCode()->is([T_RETURN, T_YIELD]))) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...TokenType::CHAIN_PART)
                       ->filter(fn(Token $t) => $t->is(TokenType::CHAIN));

        /** @var ?Token $alignWith */
        $alignWith = null;

        // Find the $first `->` with a leading newline in the chain and assign
        // its predecessor (if any) to $alignWith
        $first = $chain->find(
            function (Token $t, ?Token $next, ?Token $prev) use (&$alignWith): bool {
                if (!$t->hasNewlineBefore()) {
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
        if (!$alignWith) {
            // (unless disabled by the formatter)
            if (!$this->Formatter->AlignFirstCallInChain) {
                return;
            }
            $current = $first;
            while (!($current = $current->prevSibling())->IsNull &&
                    $first->Expression === $current->Expression &&
                    $current->is([
                        T_DOUBLE_COLON,
                        T_NAME_FULLY_QUALIFIED,
                        T_NAME_QUALIFIED,
                        T_NAME_RELATIVE,
                        T_NS_SEPARATOR,
                        T_VARIABLE,
                        ...TokenType::CHAIN_PART
                    ])) {
                $alignWith = $current;
            }
            if (!$alignWith) {
                return;
            }
            // This is safe because $alignWith->text will never contain newlines
            $adjust = mb_strlen($alignWith->text) - $this->Formatter->TabSize;
        } else {
            $alignWith->AlignedWith = $alignWith;
            $adjust = 2;
        }

        // Remove tokens before $first from the chain
        while ($chain->first()->Index < $first->Index) {
            $chain->shift();
        }

        $chain->forEach(fn(Token $t) => $t->AlignedWith = $alignWith);

        $this->Formatter->registerCallback(
            $this,
            $first,
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

        $delta = $alignWith->alignmentOffset() - $adjust;
        $callback =
            function (Token $t, ?Token $next) use ($delta) {
                if ($next) {
                    $until = $next->prev();
                } else {
                    $until = $t->pragmaticEndOfExpression();
                    if ($adjacent = $until->adjacentBeforeNewline()) {
                        $until = $adjacent->pragmaticEndOfExpression();
                    }
                }
                $t->collect($until)
                  ->forEach(
                      function (Token $_t) use ($delta, $t) {
                          $_t->LinePadding += $delta;
                          if ($_t === $t) {
                              $_t->LineUnpadding += mb_strlen($_t->text) - 2;
                          }
                      }
                  );
            };
        // Apply $delta to code between $alignWith and $first
        if ($alignWith->is(TokenType::CHAIN)) {
            $callback($alignWith->next(), $first);
        }
        $chain->forEach($callback);
    }
}
