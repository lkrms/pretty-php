<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

final class AlignArguments implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf('(', '[') || $token->hasNewlineAfterCode()) {
            return;
        }
        $align = $token->innerSiblings()->filter(
            fn(Token $t) => $t->prevCode() === $token || $t->prevCode()->is(',')
        );
        if (!$align->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }
        $align->forEach(fn(Token $t) => $this->tagToken($t));
        $this->tagToken($align->first()->parent()->startOfLine());
        $this->Formatter->registerCallback($this, $align->first(), fn() => $this->alignList($align), 600);
    }

    private function alignList(TokenCollection $list): void
    {
        /** @var Token $first */
        $first        = $list->first();
        $alignWith    = $first->parent();
        $start        = $alignWith->startOfLine();
        $alignAt      = mb_strlen(ltrim($start->collect($alignWith)->render(true, false), "\n"));
        $alignFirst   = $list->find(fn(Token $t) => $t->hasNewlineBefore());
        $hangingDelta = $alignWith->HangingIndent - $alignFirst->HangingIndent;
        $paddingDelta = $alignAt - (strlen($alignFirst->renderIndent(true)) + $hangingDelta * strlen($this->Formatter->SoftTab) + $alignFirst->LinePadding + $alignFirst->Padding);
        $list->forEach(
            function (Token $t, ?Token $prev, ?Token $next) use ($alignWith, $hangingDelta, $paddingDelta) {
                if ($hangingDelta && (!$prev || !$t->hasNewlineBefore())) {
                    $hangingDelta += 1;
                }
                $until = ($next ?: $alignWith->ClosedBy)->prev();
                $t->collect($until)->forEach(
                    function (Token $t) use ($hangingDelta, $paddingDelta) {
                        $t->HangingIndent += $hangingDelta;
                        $t->LinePadding   += $paddingDelta;
                    }
                );
            }
        );
    }

    private function tagToken(Token $token): void
    {
        $token->Tags['HasAlignedArguments'] = true;
    }
}
