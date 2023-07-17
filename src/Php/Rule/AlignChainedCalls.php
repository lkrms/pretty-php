<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
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
        if ($token !== $token->ChainOpenedBy) {
            return;
        }

        // If the first `->` in the chain has a leading newline, and alignment
        // with the start of the expression is disabled, do nothing
        if (($hasNewlineBefore = $token->hasNewlineBefore()) &&
                !$this->Formatter->AlignFirstCallInChain) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...TokenType::CHAIN_PART)
                       ->filter(fn(Token $t) => $t->is(TokenType::CHAIN));

        // If there's no `->` in the chain with a leading newline, do nothing
        if ($chain->count() < 2 ||
                !$chain->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        /** @var Token|null $alignWith */
        $alignWith = null;
        $adjust = 2;

        if ($hasNewlineBefore) {
            // Find the start of the expression
            $current = $token;
            while (($current = $current->_prevSibling) &&
                    $token->Expression === $current->Expression &&
                    $current->is([
                        T_DOUBLE_COLON,
                        T_NAME_FULLY_QUALIFIED,
                        T_NAME_QUALIFIED,
                        T_NAME_RELATIVE,
                        T_NS_SEPARATOR,
                        ...TokenType::CHAIN_PART
                    ])) {
                $alignWith = $current;
            }
            if (!$alignWith) {
                return;
            }
            $eol = $alignWith->endOfLine();
            if ($eol->IsCode &&
                    $eol->_next === $token &&
                    mb_strlen($alignWith->collect($eol)->render()) <= $this->Formatter->TabSize) {
                $token->WhitespaceBefore = WhitespaceType::NONE;
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                $alignWith = null;
            } else {
                // This is safe because $alignWith->text will never contain newlines
                $adjust = mb_strlen($alignWith->text) - $this->Formatter->TabSize;
            }
        }

        if (!$alignWith) {
            $token->AlignedWith = $alignWith = $token;
            $first = $token;
            $chain->shift();
        }

        $alignFirst = $chain->first();
        $chain->forEach(fn(Token $t) => $t->AlignedWith = $alignWith);

        $this->Formatter->registerCallback(
            $this,
            $first ?? $alignFirst,
            fn() => $this->alignChain($chain, $alignFirst, $alignWith, $adjust),
            710
        );
    }

    private function alignChain(TokenCollection $chain, Token $first, Token $alignWith, int $adjust): void
    {
        $delta = $alignWith->alignmentOffset() - $adjust;
        $callback =
            function (Token $t, ?Token $next) use ($delta) {
                if ($next) {
                    $until = $next->_prev;
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
