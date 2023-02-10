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

    public function getTokenTypes(): ?array
    {
        return TokenType::COMMENT;
    }

    public function processToken(Token $token): void
    {
        // Leave embedded comments alone
        if ($token->wasBetweenTokensOnLine(true)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter  |= WhitespaceType::SPACE;

            return;
        }

        // Don't move comments beside code to the next line
        if (!$token->wasFirstOnLine() && $token->wasLastOnLine()) {
            $token->WhitespaceBefore   |= WhitespaceType::TAB;
            $token->WhitespaceMaskPrev &= ~WhitespaceType::LINE & ~WhitespaceType::BLANK;
            $token->WhitespaceAfter    |= WhitespaceType::LINE;

            return;
        }

        // Just before rendering, copy indentation and padding values to
        // comments from code below them
        $next = $token->nextCode();
        if (!$next->isNull() &&
                !$next->isCloseBracket()) {
            $this->Formatter->registerCallback(
                $this,
                $token,
                fn() => $this->alignComment($token, $next),
                998
            );
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if (!$token->is(T_DOC_COMMENT)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->PinToCode         = !$next->isCloseBracket();

            return;
        }
        $token->WhitespaceBefore |= WhitespaceType::SPACE
            | ($token->hasNewline() ? WhitespaceType::BLANK : WhitespaceType::LINE);
        // PHPDoc comments immediately before namespace declarations are
        // generally associated with the file, not the namespace
        if ($token->next()->isDeclaration(T_NAMESPACE)) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;

            return;
        }
        if ($token->next()->isCode()) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $token->PinToCode           = !$next->isCloseBracket();
        }
    }

    private function alignComment(Token $token, Token $next): void
    {
        // Comments are usually aligned to the code below them, but switch
        // blocks are a special case, e.g.:
        //
        // ```
        // switch ($a) {
        //     //
        //     case 0:
        //     case 1:
        //         //
        //         func();
        //         // Aligns with previous statement
        //     case 2:
        //     //
        //     case 3:
        //         func2();
        //         break;
        //
        //         // Aligns with previous statement
        //
        //     case 4:
        //         func();
        //         break;
        //
        //     //
        //     default:
        //         break;
        // }
        // ```
        $prev = $token->prevCode();
        if ($next->isOneOf(T_CASE, T_DEFAULT) &&
                $prev !== $next->parent() &&
                ($next->hasBlankLineBefore() || !$prev->hasBlankLineAfter()) &&
                !($prev->is(':') && $prev->prevSibling(2)->isOneOf(T_CASE, T_DEFAULT))) {
            return;
        }

        [$token->PreIndent,
         $token->Indent,
         $token->Deindent,
         $token->HangingIndent,
         $token->LinePadding,
         $token->LineUnpadding] = [$next->PreIndent,
                                   $next->Indent,
                                   $next->Deindent,
                                   $next->HangingIndent,
                                   $next->LinePadding,
                                   $next->LineUnpadding];

        if ($token->hasNewlineAfter()) {
            $token->Padding = $next->Padding;
        }
    }
}
