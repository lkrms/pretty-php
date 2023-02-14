<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Rule\BreakBetweenMultiLineItems;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;

final class AlignLists implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return $method === self::PROCESS_TOKEN
            ? 400
            : null;
    }

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
            fn(Token $t, ?Token $prev) =>
                !$prev || $t->prevCode()
                            ->is(',')
        );
        // Apply BreakBetweenMultiLineItems if there's a trailing delimiter and
        // this is not a destructuring construct
        if ($token->ClosedBy->prevCode()->is(',') &&
            !($token->prevCode()->is(T_LIST) ||
                (($adjacent = $token->adjacent(',', ']')) && $adjacent->is('=')) ||
                (($root = $token->parentsWhile(true, '[')->last()) &&
                    $root->prevCode()->is(T_AS) &&
                    $root->parent()->prevCode()->is(T_FOREACH)))) {
            $align[] = $token->ClosedBy;
            BreakBetweenMultiLineItems::applyTo($align);

            return;
        }
        // Leave one-line lists alone
        if (!$align->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }
        $align->forEach(fn(Token $t) => $t->AlignedWith = $token);
        if (!$token->hasNewlineAfterCode()) {
            $this->Formatter->registerCallback($this, $align->first(), fn() => $this->alignList($align, $token), 710);
        }
    }

    private function alignList(TokenCollection $align, Token $token): void
    {
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
