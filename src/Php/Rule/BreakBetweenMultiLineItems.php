<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

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
            '(',
            '[',
        ];
    }

    public function processToken(Token $token): void
    {
        $items = $token->innerSiblings()
                       ->filter(fn(Token $t) => $t->prevCode()
                                                  ->is(','));

        if (!$token->hasNewlineAfter() &&
                !$items->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        self::applyTo($items);
    }

    public static function applyTo(TokenCollection $items): void
    {
        $items->forEach(
            function (Token $t) {
                $t->WhitespaceBefore           |= WhitespaceType::LINE;
                $t->WhitespaceMaskPrev         |= WhitespaceType::LINE;
                $t->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
            }
        );
    }
}
