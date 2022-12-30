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
        if ($token->isCloseTagStatementTerminator()) {
            $token->prev()->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }
        if (!($token->startsAlternativeSyntax() ||
            ($token->is(';') &&
                // Don't break after `for` expressions
                !(($parent = $token->parent())->is('(') &&
                    $parent->prevCode()->is(T_FOR)) &&
                !$token->startOfStatement()->is(T_HALT_COMPILER)))) {
            return;
        }

        $token->WhitespaceBefore   = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter   |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }
}
