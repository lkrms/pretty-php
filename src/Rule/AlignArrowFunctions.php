<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Align arrow function expressions with their definitions
 *
 * @api
 */
final class AlignArrowFunctions implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 320,
            self::CALLBACK => 600,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_FN => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * If an arrow function expression starts on a new line, a callback is
     * registered to align it with the `fn` it's associated with, or with the
     * first token on the previous line if its arguments break over multiple
     * lines.
     *
     * @prettyphp-callback Tokens in arrow function expressions are aligned with
     * the `fn` they're associated with, or with the first token on the previous
     * line if its arguments break over multiple lines.
     *
     * This is achieved by:
     *
     * - calculating the difference between the current and desired output
     *   columns of the first token in the expression
     * - applying it to the `LinePadding` of each token
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $expr = $token->nextSiblingOf(\T_DOUBLE_ARROW);
            if (!$this->Formatter->NewlineBeforeFnDoubleArrow) {
                /** @var Token */
                $expr = $expr->NextCode;
            }

            if (!$expr->hasNewlineBefore()) {
                continue;
            }

            // Allow for possibilities like `#[Foo] static fn()`
            $token = $token->skipToStartOfDeclaration();
            /** @var Token */
            $prev = $expr->PrevCode;
            /** @var Token */
            $alignWith = $token->collect($prev)
                               ->reverse()
                               ->find(fn(Token $t) =>
                                          $t === $token || (
                                              $t->Flags & Flag::CODE
                                              && $t->hasNewlineBefore()
                                          ));

            $expr->AlignedWith = $alignWith;

            $tabSize = $this->Formatter->TabSize;

            $this->Formatter->registerCallback(
                static::class,
                $expr,
                static function () use ($expr, $alignWith, $tabSize) {
                    $delta = $expr->getColumnDelta($alignWith, true) + $tabSize;
                    /** @var Token */
                    $until = $expr->EndStatement;
                    foreach ($expr->collect($until) as $t) {
                        $t->LinePadding += $delta;
                    }
                },
            );
        }
    }
}
