<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

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
            self::PROCESS_TOKENS => 380,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
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
     * This is achieved by copying the alignment target's indentation to each
     * token after making a calculated adjustment to `LinePadding`.
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
            $token = $token->skipPrevSiblingsToDeclarationStart();
            /** @var Token */
            $prev = $expr->PrevCode;
            /** @var Token */
            $alignWith = $token->collect($prev)
                               ->reverse()
                               ->find(fn(Token $t) =>
                                          $t === $token || (
                                              $t->Flags & TokenFlag::CODE
                                              && $t->hasNewlineBefore()
                                          ));

            $expr->AlignedWith = $alignWith;

            $tabSize = $this->Formatter->TabSize;
            $this->Formatter->registerCallback(
                static::class,
                $expr,
                static function () use ($expr, $alignWith, $tabSize) {
                    $offset = $alignWith->alignmentOffset(false) + $tabSize;
                    $delta = $expr->indentDelta($alignWith);
                    $delta->LinePadding += $offset;

                    /** @var Token */
                    $until = $expr->EndStatement;
                    foreach ($expr->collect($until) as $token) {
                        $delta->apply($token);
                    }
                },
            );
        }
    }
}
