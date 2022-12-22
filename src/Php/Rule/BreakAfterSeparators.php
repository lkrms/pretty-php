<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class BreakAfterSeparators implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->is(';')) {
            // Don't break after `for` expressions
            if (($parent = $token->parent())->is('(') &&
                    $parent->prevCode()->is(T_FOR)) {
                return;
            }
        } elseif ($token->is(T_CLOSE_TAG) && !$token->prev()->is(';')) {
            $token->prev()->WhitespaceAfter |= WhitespaceType::LINE;

            return;
        } elseif (!$token->startsAlternativeSyntax()) {
            return;
        }

        $token->WhitespaceBefore   = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter   |= WhitespaceType::LINE;
    }
}
