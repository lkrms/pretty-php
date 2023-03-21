<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Align consecutive object operators with the first in a chain of calls
 *
 */
final class AlignChainedCalls implements TokenRule
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

    public function getPriority(string $method): ?int
    {
        return 340;
    }

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
                       ->filter(fn(Token $t) =>
                           $t->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR]));

        // Don't process tokens in the chain multiple times
        $chain->forEach(fn(Token $t) => $t->ChainOpenedBy = $token);

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

        // If there's no `->` in the chain with a leading newline, or the first
        // `->` in the chain has a leading newline ($alignWith would be set
        // otherwise), do nothing
        if (!$first || !$alignWith) {
            return;
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
        $alignWith->AlignedWith = $alignWith;

        $this->Formatter->registerCallback(
            $this,
            $alignWith,
            fn() => $this->alignChain($chain, $first, $alignWith),
            710
        );
    }

    private function alignChain(TokenCollection $chain, Token $first, Token $alignWith): void
    {
        // Don't proceed if operators have been moved by other rules
        if ($chain->find(fn(Token $t) => !$t->hasNewlineBefore())) {
            return;
        }

        $length = mb_strlen($alignWith->text);
        $delta  = $alignWith->alignmentOffset() - $length;
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
        $callback($alignWith, $first);
        $chain->forEach($callback);
    }
}
