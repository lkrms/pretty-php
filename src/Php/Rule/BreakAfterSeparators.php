<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Add newlines after statement terminators and spaces between `for` loop
 * expressions
 *
 */
final class BreakAfterSeparators implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            ';',
            ':',
            T_CLOSE_TAG,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->isCloseTagStatementTerminator()) {
            $token->prev()->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }
        if ($token->is(';')) {
            $parent = $token->parent();
            if ($parent->is('(') && $parent->prevCode()->is(T_FOR)) {
                $token->WhitespaceAfter            |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext         |= WhitespaceType::SPACE;
                $token->next()->WhitespaceMaskPrev |= WhitespaceType::SPACE;

                return;
            }
            if ($token->startOfStatement()->is(T_HALT_COMPILER)) {
                return;
            }
        } elseif (!$token->startsAlternativeSyntax()) {
            return;
        }

        $token->WhitespaceBefore   = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter   |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }
}
