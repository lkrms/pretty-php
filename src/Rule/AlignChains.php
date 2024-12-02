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

            $chain = $token->withNextSiblingsWhile($this->Idx->ChainPart)
                           ->getAnyFrom($this->Idx->Chain);

            if (!$hasNewlineBefore && (
                $chain->count() < 2
                || !$chain->shift()->tokenHasNewlineBefore()
            )) {
                continue;
            }

            $alignWith = null;
            $offset = -2;

            if ($hasNewlineBefore) {
                $expr = TokenUtil::getChainExpression($token);
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
                    $offset = $this->Formatter->TabSize - mb_strlen($alignWith->text);
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

            $idx = $this->Idx;

            $this->Formatter->registerCallback(
                static::class,
                $token,
                static function () use ($chain, $alignWith, $offset, $idx) {
                    /** @var Token */
                    $first = $chain->first();
                    $offset = $alignWith->alignmentOffset() + $offset;
                    $delta = $first->indentDelta($alignWith);
                    $delta->LinePadding += $offset;
                    $callback = function (Token $t, ?Token $next) use ($delta) {
                        if ($next) {
                            /** @var Token */
                            $until = $next->Prev;
                        } else {
                            $until = $t->pragmaticEndOfExpression();
                            if ($adjacent = $until->adjacentBeforeNewline()) {
                                $until = $adjacent->pragmaticEndOfExpression();
                            }
                        }
                        foreach ($t->collect($until) as $_t) {
                            $delta->apply($_t);
                        }
                        if ($t->id === \T_NULLSAFE_OBJECT_OPERATOR) {
                            $t->LineUnpadding += 1;
                        }
                    };

                    // Apply $delta to code between $alignWith and $first
                    if ($idx->Chain[$alignWith->id]) {
                        assert($alignWith->Next !== null);
                        $callback($alignWith->Next, $first);
                    }

                    $chain->forEach($callback);
                },
            );
        }
    }
}
