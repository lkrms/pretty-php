<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class BreakAfterSeparators implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->is(";")) {
            return;
        }

        // Don't break after `for` expressions
        if (($bracket = end($token->BracketStack)) &&
            $bracket->is("(") &&
            $bracket->prev()->is(T_FOR)) {
            return;
        }

        $token->WhitespaceBefore   = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter   |= WhitespaceType::LINE;
    }
}
