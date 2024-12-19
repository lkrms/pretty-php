<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Align consecutive object operators in the same chain of method calls
 *
 * @api
 */
final class AlignChains implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 340,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return $idx->Chain;
    }

    /**
     * Apply the rule to the given tokens
     *
     * If there are no object operators with a leading newline in a chain of
     * method calls, or if the first object operator in the chain has a leading
     * newline and `AlignChainAfterNewline` is disabled, no action is taken.
     *
     * Otherwise, if the first object operator in the chain has a leading
     * newline, it is removed if horizontal space on subsequent lines would be
     * saved. Then, a callback is registered to align object operators in the
     * chain with:
     *
     * - the first object operator (if it has no leading newline)
     * - the expression dereferenced by the first object operator (if it doesn't
     *   break over multiple lines), or
     * - the first token on the line before the first object operator
     *
     * @prettyphp-callback Object operators in a chain of method calls are
     * aligned with a given token.
     *
     * This is achieved by:
     *
     * - calculating the difference between the first object operator's current
     *   output column and its desired output column
     * - applying it to the `LinePadding` of each object operator and its
     *   adjacent tokens
     * - incrementing `LineUnpadding` for any `?->` operators, to accommodate
     *   the extra character
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token !== $token->Data[TokenData::CHAIN_OPENED_BY]) {
                continue;
            }

            $hasNewlineBefore = $token->hasNewlineBefore();

            if (
                $hasNewlineBefore
                && !$this->Formatter->AlignChainAfterNewline
            ) {
                continue;
            }

            $chain = $token->withNextSiblingsFrom($this->Idx->ChainPart)
                           ->getAnyFrom($this->Idx->Chain);

            if (!$hasNewlineBefore && (
                $chain->count() < 2
                || !$chain->shift()->tokenHasNewlineBefore()
            )) {
                continue;
            }

            $alignWith = null;
            $offset = 0;

            if ($hasNewlineBefore) {
                $expr = TokenUtil::getOperatorExpression($token);
                /** @var Token */
                $prev = $token->PrevCode;
                /** @var Token */
                $alignWith = $expr->collect($prev)
                                  ->reverse()
                                  ->find(fn(Token $t) =>
                                             $t === $expr || (
                                                 $t->Flags & TokenFlag::CODE
                                                 && $t->hasNewlineBefore()
                                             ));

                $eol = $alignWith->endOfLine();
                if (
                    $eol->Flags & TokenFlag::CODE
                    && $eol->Next === $token
                    && mb_strlen($alignWith->collect($eol)->render()) <= $this->Formatter->TabSize
                ) {
                    $token->Whitespace |= Space::NONE_BEFORE;
                    $alignWith = null;
                } else {
                    $offset = $this->Formatter->TabSize + 2 - mb_strlen($alignWith->text);
                }
            }

            if (!$alignWith) {
                if ($chain->count() < 2) {
                    continue;
                }
                $token->AlignedWith = $alignWith = $token;
                $chain = $chain->shift();
            }

            foreach ($chain as $t) {
                $t->AlignedWith = $alignWith;
            }

            $until = TokenUtil::getOperatorEndExpression($token);
            $idx = $this->Idx;

            $this->Formatter->registerCallback(
                static::class,
                $token,
                static function () use (
                    $chain,
                    $alignWith,
                    $offset,
                    $until,
                    $idx
                ) {
                    /** @var Token */
                    $first = $chain->first();
                    $delta = $first->getColumnDelta($alignWith, false) + $offset;
                    if ($first->id === \T_NULLSAFE_OBJECT_OPERATOR) {
                        $delta++;
                    }
                    $callback = static function (
                        Token $token,
                        ?Token $next
                    ) use ($until, $delta) {
                        if ($next) {
                            /** @var Token */
                            $until = $next->Prev;
                        } else {
                            while ($adjacent = $until->adjacentBeforeNewline()) {
                                $until = TokenUtil::getOperatorEndExpression($adjacent);
                            }
                        }
                        foreach ($token->collect($until) as $t) {
                            $t->LinePadding += $delta;
                        }
                        if ($token->id === \T_NULLSAFE_OBJECT_OPERATOR) {
                            $token->LineUnpadding++;
                        }
                    };

                    // If the second and subsequent object operators in the
                    // chain are being aligned with the first, apply the
                    // callback to any tokens between the first and second
                    if ($idx->Chain[$alignWith->id]) {
                        /** @var Token */
                        $next = $alignWith->Next;
                        $callback($next, $first);
                    }

                    $chain->forEach($callback);
                },
            );
        }
    }
}
