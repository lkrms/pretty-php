<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Align consecutive object operators in the same chain of method calls
 *
 * @api
 */
final class AlignChains implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 340;

            case self::CALLBACK:
                return 710;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return $idx->Chain;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token !== $token->Data[TokenData::CHAIN_OPENED_BY]) {
                continue;
            }

            $hasNewlineBefore = $token->hasNewlineBefore();

            // Do nothing if the first `->` in the chain has a leading newline
            // and alignment with the start of the expression is disabled
            if ($hasNewlineBefore && !$this->Formatter->AlignFirstCallInChain) {
                continue;
            }

            $chain = $token->withNextSiblingsWhile($this->Idx->ChainPart)
                           ->getAnyFrom($this->Idx->Chain);

            // Do nothing if there's no `->` in the chain with a leading newline
            if (!$hasNewlineBefore && (
                $chain->count() < 2 || !$chain->slice(1)->tokenHasNewlineBefore()
            )) {
                continue;
            }

            /** @var Token|null $alignWith */
            $alignWith = null;
            $offset = -2;

            if ($hasNewlineBefore) {
                $start = $token->pragmaticStartOfExpression();
                // If the expression being dereferenced breaks over multiple
                // lines, align with the start of the previous line
                assert($token->Prev !== null);
                /** @var Token */
                $alignWith = $start->collect($token->Prev)
                                   ->reverse()
                                   ->find(fn(Token $t) =>
                                              $t === $start
                                                  || ($t->Flags & TokenFlag::CODE && $t->hasNewlineBefore()));

                // Collapse the first `->` in the chain if it would save space
                $eol = $alignWith->endOfLine();
                if (
                    $eol->Flags & TokenFlag::CODE
                    && $eol->Next === $token
                    && mb_strlen($alignWith->collect($eol)->render()) <= $this->Formatter->TabSize
                ) {
                    $token->WhitespaceBefore = WhitespaceType::NONE;
                    $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                    $alignWith = null;
                } else {
                    // Safe because $alignWith->text can't have newlines
                    $offset = $this->Formatter->TabSize - mb_strlen($alignWith->text);
                }
            }

            if (!$alignWith) {
                if ($chain->count() < 2) {
                    continue;
                }
                $token->AlignedWith = $alignWith = $token;
                $chain->shift();
            }

            $chain->forEach(fn(Token $t) => $t->AlignedWith = $alignWith);

            $this->Formatter->registerCallback(
                static::class,
                $token,
                fn() => $this->alignChain($chain, $alignWith, $offset),
            );
        }
    }

    private function alignChain(
        TokenCollection $chain,
        Token $alignWith,
        int $offset
    ): void {
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
        if ($this->Idx->Chain[$alignWith->id]) {
            assert($alignWith->Next !== null);
            $callback($alignWith->Next, $first);
        }

        $chain->forEach($callback);
    }
}
