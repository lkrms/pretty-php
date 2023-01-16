<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddStandardWhitespace implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isOneOf(...TokenType::ADD_SPACE_AROUND)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter  |= WhitespaceType::SPACE;
        }

        if ($token->isOneOf(...TokenType::ADD_SPACE_BEFORE)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        }

        if ($token->isOneOf(...TokenType::ADD_SPACE_AFTER)) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        if (($token->isOpenBracket() && !$token->isStructuralBrace()) ||
                $token->isOneOf(...TokenType::SUPPRESS_SPACE_AFTER)) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
        }

        if (($token->isCloseBracket() && !$token->isStructuralBrace()) ||
                $token->isOneOf(...TokenType::SUPPRESS_SPACE_BEFORE)) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        }

        if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) {
            if ($token->is(T_OPEN_TAG) &&
                    ($declare = $token->next())->is(T_DECLARE) &&
                    ($end = $declare->nextSibling(2)) === $declare->endOfStatement()) {
                $token->WhitespaceAfter   |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                $token                     = $end;
            }
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        if ($token->is(T_CLOSE_TAG)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }

        if ($token->is(':') && $token->inLabel()) {
            $token->WhitespaceAfter    |= WhitespaceType::LINE;
            $token->WhitespaceMaskNext |= WhitespaceType::LINE;

            return;
        }

        // Suppress whitespace in the directive section of `declare` blocks
        if ($token->is('(') && $token->prevCode()->is(T_DECLARE)) {
            $first = $token->inner()
                           ->forEach(fn(Token $t) =>
                               $t->WhitespaceMaskNext = WhitespaceType::NONE)
                           ->first();
            !$first || $first->WhitespaceMaskPrev = WhitespaceType::NONE;
        }
    }
}
