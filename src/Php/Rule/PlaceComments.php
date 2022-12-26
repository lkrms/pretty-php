<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class PlaceComments implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
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

        $next = $token->nextCode();
        if (!($next->isNull() || $next->isCloseBracket())) {
            $this->Formatter->registerCallback($this, $token, fn() => $this->alignComment($token, $next));
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
        if ($token->next()->isDeclaration(T_NAMESPACE)) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;

            return;
        }
        if ($token->next()->isCode()) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $token->PinToCode           = true;
        }
    }

    private function alignComment(Token $token, Token $next): void
    {
        [$token->Indent, $token->Deindent, $token->HangingIndent, $token->Padding] = [
            $next->Indent,
            $next->Deindent,
            $next->HangingIndent,
            $next->Padding,
        ];
    }
}
