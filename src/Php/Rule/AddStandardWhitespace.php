<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddStandardWhitespace implements TokenRule
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

        if ($token->isCloseBracket()) {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        }

        if ($token->isOneOf(...TokenType::DECLARATION)) {
            /** @var Token $start */
            $start = $token->prevSiblingsWhile(true, T_STRING, ...TokenType::DECLARATION)->last();
            if ($start->is(T_USE) && $start->prevCode()->is(")")) {
                return;
            }
            if ($start->hasTags("declaration")) {
                return;
            }
            $start->Tags[] = "declaration";

            $strings = $start->nextSiblingsWhile(true, T_STRING, ...TokenType::DECLARATION);
            /** @var Token $last */
            $last    = $strings->last();
            if ($last->isOneOf(T_FN, T_FUNCTION)) {
                return;
            }
            // If the same DECLARATION_CONDENSE token types appear in this
            // statement as in the last one, don't add a blank line between them
            $types = $strings->getAnyOf(...TokenType::DECLARATION_CONDENSE)->getTypes();
            if ($types && $start->prevCode()->stringsAfterLastStatement()->hasOneOf(...$types)) {
                return;
            }
            $start->WhitespaceBefore |= WhitespaceType::BLANK;
        }

        if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) {
            if ($token->is(T_OPEN_TAG) &&
                ($declare = $token->next())->is(T_DECLARE) &&
                ($end = $declare->nextSibling(2)) === $declare->endOfStatement()) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token                   = $end;
            }
            $token->WhitespaceAfter |= WhitespaceType::BLANK;

            return;
        }
    }
}
