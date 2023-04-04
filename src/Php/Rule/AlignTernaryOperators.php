<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Align ternary operators with their expressions
 *
 * This rule also moves ternary operators to the start of a new line if they
 * have a counterpart with a leading newline.
 */
final class AlignTernaryOperators implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 380;
    }

    public function getTokenTypes(): ?array
    {
        return [T['?']];
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

        // Similar code in `AddHangingIndentation` also prevents this:
        //
        // ```
        // $a
        //   ?: $b
        //     ?: $c
        // ```
        $prevTernary =
            $token->prevSiblingsUntil(
                      fn(Token $t) =>
                          $t->Statement !== $token->Statement
                  )
                  ->filter(
                      fn(Token $t) =>
                          $t->IsTernaryOperator &&
                              $t->TernaryOperator1 === $t &&
                              $t->TernaryOperator2->Index < $token->Index
                  )
                  ->last();
        // If previous ternary operators in this scope have already been
        // aligned, do nothing
        if ($prevTernary && $prevTernary->AlignedWith) {
            $this->setAlignedWith($token, $prevTernary->AlignedWith);

            return;
        }
        $alignWith = ($prevTernary ?: $token)->prevCode()
                                             ->pragmaticStartOfExpression(true);

        $this->setAlignedWith($token, $alignWith);

        $this->Formatter->registerCallback(
            $this,
            $token,
            fn() => $this->alignOperators($token, $alignWith),
            710
        );
    }

    private function setAlignedWith(Token $token, Token $alignWith): void
    {
        $token->AlignedWith                   = $alignWith;
        $token->TernaryOperator2->AlignedWith = $alignWith;
    }

    private function alignOperators(Token $token, Token $alignWith): void
    {
        $delta = $alignWith->alignmentOffset(false) + $this->Formatter->TabSize;
        // Find
        // - the last token
        // - in the third expression
        // - of the last ternary expression
        // - encountered in this scope
        $current = $token;
        do {
            $until = $current->TernaryOperator2->EndExpression ?: $current;
        } while ($until !== $current &&
            ($current = $until->nextSibling())->IsTernaryOperator &&
            $current->TernaryOperator1 === $current);
        // And without breaking out of an unenclosed control structure body,
        // proceed to the end of the expression
        if (!$until->nextSibling()->IsTernaryOperator) {
            $until = $until->pragmaticEndOfExpression(true);
        }

        $token->collect($until)
              ->forEach(fn(Token $t) =>
                            $t->LinePadding += $delta);
    }
}
