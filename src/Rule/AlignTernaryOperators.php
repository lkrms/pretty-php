<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;

/**
 * Align ternary operators with their expressions
 *
 */
final class AlignTernaryOperators implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 380;
    }

    public function getTokenTypes(): array
    {
        return [T_QUESTION];
    }

    public function processToken(Token $token): void
    {
        if (!$token->IsTernaryOperator) {
            return;
        }

        // If neither operator is at the start of a line, do nothing
        if (!($token->hasNewlineBefore() ||
                $token->TernaryOperator2->hasNewlineBefore())) {
            return;
        }

        // If previous ternary operators in this scope have already been
        // aligned, do nothing
        $prevTernary = HangingIndentation::getTernaryContext($token);
        if ($prevTernary && $prevTernary->AlignedWith) {
            $this->setAlignedWith($token, $prevTernary->AlignedWith);
            return;
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

    private function setAlignedWith(Token $token, Token $alignWith): void
    {
        $token->AlignedWith = $alignWith;
        $token->TernaryOperator2->AlignedWith = $alignWith;
    }

    private function alignOperators(Token $token, Token $until, Token $alignWith): void
    {
        $delta =
            $alignWith->alignmentOffset(false)
                + $this->Formatter->TabSize;

        $token->collect($until)
              ->forEach(fn(Token $t) => $t->LinePadding += $delta);
    }
}
