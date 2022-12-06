<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddEssentialWhitespace extends AbstractTokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->hasNewlineAfter()) {
            return;
        }

        if ($token->isOneLineComment() && !$token->next()->is(T_CLOSE_TAG)) {
            $token->WhitespaceAfter            |= WhitespaceType::LINE;
            $token->WhitespaceMaskNext         |= WhitespaceType::LINE;
            $token->next()->WhitespaceMaskPrev |= WhitespaceType::LINE;

            return;
        }

        if ($token->hasWhitespaceAfter()) {
            return;
        }

        if ($token->is(T_OPEN_TAG) ||
                preg_match('/^[a-zA-Z0-9\\\\_\x80-\xff]{2}$/', substr($token->Code, -1) . substr($token->next()->Code, 0, 1))) {
            $token->WhitespaceAfter            |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext         |= WhitespaceType::SPACE;
            $token->next()->WhitespaceMaskPrev |= WhitespaceType::SPACE;
        }
    }
}
