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

    public function getTokenTypes(): ?array
    {
        return [
            '(',
            '[',
        ];
    }

    public function processToken(Token $token): void
    {
        $align = $token->innerSiblings()->filter(
            fn(Token $t, ?Token $prev) => !$prev || $t->prevCode()->is(',')
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
            function (Token $t, ?Token $prev) use ($token) {
                if ($prev) {
                    $t->WhitespaceBefore           |= WhitespaceType::LINE;
                    $t->WhitespaceMaskPrev         |= WhitespaceType::LINE;
                    $t->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
                }
                $t->AlignedWith = $token;
            }
        );
        if (!$token->hasNewlineAfterCode()) {
            $this->Formatter->registerCallback($this, $align->first(), fn() => $this->alignList($align, $token), 710);
        }
    }

    private function alignList(TokenCollection $align, Token $token): void
    {
        // Don't proceed if items have been moved by other rules
        if ($align->find(fn(Token $t, ?Token $prev) => $prev && !$t->hasNewlineBefore())) {
            return;
        }

        $delta = $token->alignmentOffset();
        $align->forEach(function (Token $t, ?Token $prev, ?Token $next) use ($delta) {
            if ($next) {
                $until = $next->prev(2);
            } else {
                $until = $t->endOfExpression();
                if ($adjacent = $until->adjacentBeforeNewline()) {
                    $until = $adjacent->endOfExpression();
                }
            }
            $t->collect($until)
              ->forEach(fn(Token $_t) =>
                  $_t->LinePadding += $delta);
        });
    }
}
