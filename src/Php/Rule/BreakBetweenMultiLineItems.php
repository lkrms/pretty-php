<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add a newline between every item in a multi-line delimited list
 *
 */
final class BreakBetweenMultiLineItems implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            T['('],
            T['['],
        ];
    }

    public function processToken(Token $token): void
    {
        $items = $token->innerSiblings()
                       ->filter(fn(Token $t) => $t->prevCode()
                                                  ->is(T[',']));

        if (!$token->hasNewlineAfter() &&
                !$items->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        $items->addWhitespaceBefore(WhitespaceType::LINE);
    }
}
