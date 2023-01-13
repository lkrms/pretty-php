<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

final class BreakAfterSeparators implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isCloseTagStatementTerminator()) {
            $token->prev()->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
        } elseif (($token->is(';') &&
                !(($parent = $token->parent())->is('(') && $parent->prevCode()->is(T_FOR)) &&
                !$token->startOfStatement()->is(T_HALT_COMPILER)) ||
                $token->startsAlternativeSyntax()) {
            $token->WhitespaceBefore   = WhitespaceType::NONE;
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceAfter   |= WhitespaceType::LINE | WhitespaceType::SPACE;
        }
    }
}
