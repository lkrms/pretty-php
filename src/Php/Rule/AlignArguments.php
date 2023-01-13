<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

class AlignArguments implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf('(', '[') || $token->hasNewlineAfterCode()) {
            return;
        }
        $align = $token->innerSiblings()->filter(fn(Token $t) => $t->hasNewlineBefore());
        if (!count($align) ||
            count($align->filter(fn(Token $t) => !$t->prevCode()->is(','))) ||
            count($token->inner()->filter(
                fn(Token $t) => $t->hasNewlineBefore() && !$align->has($t) && $t->prevCode()->isOneOf('(', '[', '{')
            ))) {
            return;
        }
        $align->forEach(fn(Token $t) => $this->tagToken($t));
        $this->tagToken($align->first()->parent()->startOfLine());
        $this->Formatter->registerCallback($this, $align->first(), fn() => $this->alignList($align));
    }

    private function alignList(TokenCollection $list): void
    {
        /** @var Token $first */
        $first     = $list->first();
        $alignWith = $first->parent();
        $start     = $alignWith->startOfLine();
        $alignAt   = mb_strlen($start->collect($alignWith)->render(true));
        $list->forEach(
            function (Token $t) use ($alignWith, $alignAt) {
                [$t->PreIndent, $t->Indent, $t->Deindent, $t->HangingIndent] =
                    [$alignWith->PreIndent, $alignWith->Indent, $alignWith->Deindent, $alignWith->HangingIndent];
                $t->Padding += $alignAt - (strlen($t->renderIndent(true)) + $t->Padding);
            }
        );
    }

    private function tagToken(Token $token): void
    {
        $token->Tags['HasAlignedArguments'] = true;
    }
}
