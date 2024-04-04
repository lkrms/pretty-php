<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align ternary and null coalescing operators with their expressions
 *
 * @api
 */
final class AlignTernaryOperators implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 380;

            case self::CALLBACK:
                return 710;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_QUESTION,
            \T_COALESCE,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->id === \T_QUESTION
                && !($token->Flags & TokenFlag::TERNARY_OPERATOR)
            ) {
                continue;
            }

            // Do nothing if none of the operators in question are at the start
            // of a line
            if (!$token->hasNewlineBefore()
                && ($token->id === \T_COALESCE
                    || !$token->TernaryOperator2->hasNewlineBefore())) {
                continue;
            }

            // If previous ternary or null coalescing operators in this scope
            // have already been aligned, do nothing
            $prevTernary = HangingIndentation::getTernaryContext($token);
            if ($prevTernary && $prevTernary->AlignedWith) {
                $this->setAlignedWith($token, $prevTernary->AlignedWith);
                continue;
            }

            $alignWith =
                ($prevTernary ?: $token)
                    ->PrevCode
                    ->pragmaticStartOfExpression(true);

            $this->setAlignedWith($token, $alignWith);
            $until = HangingIndentation::getTernaryEndOfExpression($token);

            $this->Formatter->registerCallback(
                static::class,
                $token,
                fn() => $this->alignOperators($token, $until, $alignWith)
            );
        }
    }

    private function setAlignedWith(Token $token, Token $alignWith): void
    {
        $token->AlignedWith = $alignWith;
        if ($token->TernaryOperator2) {
            $token->TernaryOperator2->AlignedWith = $alignWith;
        }
    }

    private function alignOperators(Token $token, Token $until, Token $alignWith): void
    {
        $delta =
            $alignWith->alignmentOffset(false)
            + $this->Formatter->TabSize;

        foreach ($token->collect($until) as $token) {
            $token->LinePadding += $delta;
        }
    }
}
