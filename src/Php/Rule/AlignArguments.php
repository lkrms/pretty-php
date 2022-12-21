<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

class AlignArguments implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var TokenCollection[]
     */
    private $Lists = [];

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf('(', '[') || $token->hasNewlineAfter()) {
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
        $this->Lists[] = $align;
    }

    public function beforeRender(): void
    {
        foreach ($this->Lists as $list) {
            /** @var Token $first */
            $first     = $list->first();
            $alignWith = $first->parent();
            $start     = $alignWith->startOfLine();
            $alignAt   = strlen($start->collect($alignWith)->render());
            $list->forEach(fn(Token $t) => $t->Padding += $alignAt - (strlen($t->renderIndent()) + $t->Padding));
        }
    }

    public function clear(): void
    {
        $this->Lists = [];
    }
}
