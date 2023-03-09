<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Add a newline after the open bracket of a multi-line delimited list
 *
 */
final class BreakBeforeMultiLineList implements TokenRule
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
        if (!$token->innerSiblings()->find(
            fn(Token $t) =>
                $t->prevCode()->is(T[',']) && $t->hasNewlineBefore()
        )) {
            return;
        }

        $token->WhitespaceAfter            |= WhitespaceType::LINE;
        $token->WhitespaceMaskNext         |= WhitespaceType::LINE;
        $token->next()->WhitespaceMaskPrev |= WhitespaceType::LINE;
    }
}
