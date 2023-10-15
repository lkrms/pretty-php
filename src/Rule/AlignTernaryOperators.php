<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align ternary and null coalescing operators with their expressions
 *
 * @api
 */
final class AlignTernaryOperators implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 380;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_QUESTION,
            T_COALESCE,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === T_QUESTION && !$token->IsTernaryOperator) {
                continue;
            }

            // Do nothing if none of the operators in question are at the start
            // of a line
            if (!$token->hasNewlineBefore() &&
                ($token->id === T_COALESCE ||
                    !$token->TernaryOperator2->hasNewlineBefore())) {
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
                    ->_prevCode
                    ->pragmaticStartOfExpression(true);

            $this->setAlignedWith($token, $alignWith);
            $until = HangingIndentation::getTernaryEndOfExpression($token);

            $this->Formatter->registerCallback(
                $this,
                $token,
                fn() => $this->alignOperators($token, $until, $alignWith),
                710
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
