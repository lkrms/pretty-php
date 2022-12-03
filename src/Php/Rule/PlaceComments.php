<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class PlaceComments implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->isOneOf(...TokenType::COMMENT)) {
            return;
        }

        // Leave embedded comments alone
        if ($token->wasBetweenTokensOnLine()) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter  |= WhitespaceType::SPACE;

            return;
        }

        // Don't move comments beside code to the next line
        if (!$token->wasFirstOnLine() && $token->wasLastOnLine() && $token->isOneLineComment(true)) {
            $token->WhitespaceBefore |= WhitespaceType::TAB;
            $token->WhitespaceAfter  |= WhitespaceType::LINE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if (!$token->is(T_DOC_COMMENT)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
            return;
        }
        $token->WhitespaceBefore   |= $token->hasNewline() ? WhitespaceType::BLANK : WhitespaceType::LINE;
        $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
    }
}
