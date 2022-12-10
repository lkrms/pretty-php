<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class PlaceComments extends AbstractTokenRule
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
            $token->PinToCode         = true;

            return;
        }
        $token->WhitespaceBefore |= $token->hasNewline() ? WhitespaceType::BLANK : WhitespaceType::LINE;
        // PHPDoc comments immediately before namespace declarations are
        // generally associated with the file, not the namespace
        if ($token->nextCode()->isDeclaration(T_NAMESPACE)) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;

            return;
        }
        $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        $token->PinToCode           = true;
    }
}
