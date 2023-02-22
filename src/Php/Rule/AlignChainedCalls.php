<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

class AlignChainedCalls implements TokenRule
{
    use TokenRuleTrait;

    private const CHAIN_TOKEN_TYPE = [
        T['('],
        T['['],
        T['{'],
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_STRING,
    ];

    public function getTokenTypes(): ?array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->ChainOpenedBy) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...self::CHAIN_TOKEN_TYPE)
                       ->filter(fn(Token $t) => $t->is([T_OBJECT_OPERATOR,
                                                        T_NULLSAFE_OBJECT_OPERATOR]));

        // Don't process tokens in the chain multiple times
        $chain->forEach(fn(Token $t) => $t->ChainOpenedBy = $token);

        /** @var ?Token $alignWith */
        $alignWith = null;

        // Find the $first `->` with a leading newline in the chain and assign
        // its predecessor (if any) to $alignWith
        $first = $chain->find(
            function (Token $token, ?Token $prev) use (&$alignWith): bool {
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
        // would be set otherwise), look for the closest `::` in the same
        // expression
        $adjust = 0;
        if (!$alignWith) {
            $alignWith = $first->prevSiblingsWhile(T['('],
                                                   T['['],
                                                   T['{'],
                                                   T_DOUBLE_COLON,
                                                   T_STRING)
                               ->getFirstOf(T_DOUBLE_COLON);

            // If the token or block before `::` begins and ends on the same
            // line as `::` and occupies more than 7 columns, align with it
            // instead of `::`
            if ($alignWith) {
                $prev = $alignWith->prevCode()->canonical()->collect($alignWith);
                if (!$prev->hasInnerNewline() &&
                        ($length = mb_strlen($prev->render(true))) > 7 + 2) {
                    $adjust = $this->Formatter->TabSize + 2 - $length;
                }
            }
        }

        // Remove tokens before $first from the chain
        while ($chain->first()->Index < $first->Index) {
            $chain->shift();
        }

        // Apply a leading newline to the remaining tokens
        $chain->forEach(
            function (Token $t, ?Token $prev) use ($first, $alignWith) {
                if ($prev) {
                    $t->WhitespaceBefore |= WhitespaceType::LINE;
                }
                $t->AlignedWith = $alignWith ?: $first;
            }
        );
        if ($alignWith) {
            $alignWith->AlignedWith = $alignWith;
        }

        $this->Formatter->registerCallback($this,
                                           $alignWith ?: $first,
                                           fn() => $this->alignChain($chain, $first, $alignWith, $adjust),
                                           710);
    }

    private function alignChain(TokenCollection $chain, Token $first, ?Token $alignWith, int $adjust): void
    {
        // Don't proceed if operators have been moved by other rules
        if ($chain->find(fn(Token $t) => !$t->hasNewlineBefore())) {
            return;
        }

        if ($alignWith) {
            $length = mb_strlen($alignWith->text);
            $delta  = $alignWith->alignmentOffset() - $length + $adjust;
        } else {
            $length = 2;
            $delta  = $this->Formatter->TabSize;
        }

        $callback = function (Token $t, ?Token $prev, ?Token $next) use ($length, $delta) {
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
        if ($alignWith) {
            // Apply $delta to code between $alignWith and $first
            $callback($alignWith, null, $first);
        }
        $chain->forEach($callback);
    }
}
