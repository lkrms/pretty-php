<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Align ternary and null coalescing operators with their expressions
 *
 * @api
 */
final class AlignTernaryOperators implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 324,
            self::CALLBACK => 600,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_QUESTION => true,
            \T_COALESCE => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * Apply the rule to the given tokens
     *
     * If a ternary or null coalescing operator has a leading newline, a
     * callback is registered to align it with its expression, or with the first
     * token on the previous line if its expression breaks over multiple lines.
     *
     * @prettyphp-callback Ternary and null coalescing operators with leading
     * newlines are aligned with their expressions, or with the first token on
     * the previous line if their expressions break over multiple lines.
     *
     * This is achieved by:
     *
     * - calculating the difference between the current and desired output
     *   columns of the operator
     * - applying it to the `LinePadding` of the operator and its adjacent
     *   tokens
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Do nothing if the token is a non-ternary `?` or is not at the
            // start of a line
            if ((
                $token->id === \T_QUESTION
                && !($token->Flags & TokenFlag::TERNARY)
            ) || !$token->hasNewlineBefore()) {
                continue;
            }

            // If ternary or null coalescing operators in this statement have
            // already been aligned, do nothing
            $prevTernary = TokenUtil::getTernaryContext($token);
            if ($prevTernary && $prevTernary->AlignedWith) {
                $this->setAlignedWith($token, $prevTernary->AlignedWith);
                continue;
            }

            $expr = TokenUtil::getTernaryExpression($prevTernary ?? $token);
            /** @var Token */
            $prev = ($prevTernary ?? $token)->PrevCode;
            /** @var Token */
            $alignWith = $expr->collect($prev)
                              ->reverse()
                              ->find(fn(Token $t) =>
                                         $t === $expr || (
                                             $t->Flags & TokenFlag::CODE
                                             && $t->hasNewlineBefore()
                                         ));

            $this->setAlignedWith($token, $alignWith);

            $until = TokenUtil::getTernaryEndExpression($token);
            $tabSize = $this->Formatter->TabSize;

            $this->Formatter->registerCallback(
                static::class,
                $token,
                static function () use ($token, $alignWith, $until, $tabSize) {
                    while ($adjacent = $until->adjacentBeforeNewline()) {
                        $until = TokenUtil::getOperatorEndExpression($adjacent);
                    }
                    $tokens = $token->collect($until);
                    ($callback = static function () use ($token, $alignWith, $tabSize, $tokens) {
                        $delta = $token->getColumnDelta($alignWith, true) + $tabSize;
                        if ($delta) {
                            foreach ($tokens as $token) {
                                $token->LinePadding += $delta;
                            }
                        }
                    })();
                    $alignWith->Data[TokenData::ALIGNMENT_CALLBACKS][] = $callback;
                }
            );
        }
    }

    private function setAlignedWith(Token $token, Token $alignWith): void
    {
        $token->AlignedWith = $alignWith;
        if ($token->Flags & TokenFlag::TERNARY) {
            $other = $token->Data[TokenData::OTHER_TERNARY];
            if ($other->hasNewlineBefore()) {
                $other->AlignedWith = $alignWith;
            }
        }
    }
}
