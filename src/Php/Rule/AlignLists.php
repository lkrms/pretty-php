<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

final class AlignLists implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf('(', '[')) {
            return;
        }
        $align = $token->innerSiblings()->filter(
            fn(Token $t) => $t->prevCode() === $token || $t->prevCode()->is(',')
        );
        // Take a trailing delimiter as a request for newlines between items
        if (!$token->ClosedBy->prevCode()->is(',')) {
            // Suppress newlines between array items unless the array has a
            // trailing delimiter, opens with a line break, or has a line break
            // after the first item
            if ($token->isArrayOpenBracket() &&
                    !$token->hasNewlineAfterCode() &&
                    !(($second = $align->nth(1)) && $second->hasNewlineBefore())) {
                $align->forEach(
                    fn(Token $t) =>
                        $t->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE
                );

                return;
            }
            if (!$align->find(fn(Token $t) => $t->hasNewlineBefore())) {
                return;
            }
        }
        $align->forEach(
            function (Token $t) use ($token) {
                if ($t->prevCode() !== $token) {
                    $t->WhitespaceBefore           |= WhitespaceType::LINE;
                    $t->WhitespaceMaskPrev         |= WhitespaceType::LINE;
                    $t->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
                }
                $t->AlignedWith = $token;
            }
        );
        if (!$token->hasNewlineAfterCode()) {
            $this->Formatter->registerCallback($this, $align->first(), fn() => $this->alignList($align), 710);
        }
    }

    private function alignList(TokenCollection $align): void
    {
        /** @var Token $first */
        $first     = $align->first();
        $alignWith = $first->parent();
        $start     = $alignWith->startOfLine();
        $alignAt   = mb_strlen(ltrim($start->collect($alignWith)->render(true, false), "\n"));
        $align->forEach(
            function (Token $t, ?Token $prev, ?Token $next) use ($alignWith, $start, $alignAt) {
                $until = ($next ?: $alignWith->ClosedBy)->prev();

                $overhang = 0;
                if (!$prev || !$t->hasNewlineBefore()) {
                    $nextLine = $t->endOfLine()->next();
                    if ($nextLine->isNull() || $nextLine->Index > $until->Index) {
                        return;
                    }
                    $t        = $nextLine;
                    $overhang = 1;
                }

                $hangingDelta = $start->HangingIndent - $t->HangingIndent + $overhang;
                $paddingDelta = $alignAt
                    - (strlen($t->renderIndent(true))
                        // Subtract $overhang here to ensure hanging indentation
                        // within arguments is preserved
                        + ($hangingDelta - $overhang) * $this->Formatter->TabSize
                        + $t->LinePadding
                        + $t->Padding);
                $overhangingDiff = array_diff_key($t->OverhangingParents, $start->OverhangingParents);

                $t->collect($until)->forEach(
                    function (Token $t) use ($hangingDelta, $paddingDelta, $overhangingDiff) {
                        $t->HangingIndent     += $hangingDelta;
                        $t->LinePadding       += $paddingDelta;
                        $t->OverhangingParents = array_diff_key($t->OverhangingParents, $overhangingDiff);
                    }
                );
            }
        );
    }
}
