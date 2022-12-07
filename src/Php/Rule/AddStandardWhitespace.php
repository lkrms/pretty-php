<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddStandardWhitespace extends AbstractTokenRule
{
    public function __invoke(Token $token): void
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

        if ($token->isOpenBracket() ||
                $token->isOneOf(...TokenType::SUPPRESS_SPACE_AFTER)) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
        }

        if ($token->isCloseBracket() ||
                $token->isOneOf(...TokenType::SUPPRESS_SPACE_BEFORE)) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        }

        if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) {
            if ($token->is(T_OPEN_TAG) &&
                    ($declare = $token->next())->is(T_DECLARE) &&
                    ($end = $declare->nextSibling(2)) === $declare->endOfStatement()) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token                   = $end;
            }
            $token->WhitespaceAfter |= WhitespaceType::LINE;

            return;
        }

        if ($token->is(T_CLOSE_TAG)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
        }
    }
}
