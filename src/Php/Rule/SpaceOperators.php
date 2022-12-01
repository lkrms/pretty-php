<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class SpaceOperators implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!($token->isOperator() || $token->isOneOf("?", ...TokenType::AMPERSAND)) ||
            $token->parent()->prev()->is(T_DECLARE))
        {
            return;
        }

        // Suppress whitespace after ampersands related to returning, assigning
        // or passing by reference
        if ($token->isOneOf(...TokenType::AMPERSAND) &&
            ($token->prevCode()->is(T_FUNCTION) ||
                $token->isUnaryContext() ||
                ($token->next()->is(T_VARIABLE) &&
                    $token->inDeclaration(T_FUNCTION) &&
                    !$token->sinceLastStatement()->hasOneOf("="))))
        {
            $token->WhitespaceBefore  |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }
        // Suppress whitespace between types in unions and intersections
        if ($token->isOneOf("|", ...TokenType::AMPERSAND) &&
            $token->inDeclaration(T_FUNCTION) && !$token->sinceLastStatement()->hasOneOf("="))
        {
            $token->WhitespaceMaskNext = $token->WhitespaceMaskPrev = WhitespaceType::NONE;

            return;
        }
        if ($token->is("?") && !$token->isTernaryOperator())
        {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        if ($token->isUnaryOperator() && !$token->nextCode()->isUnaryOperator())
        {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;

        // Collapse ternary operators if there is nothing between `?` and `:`
        if ($token->isTernaryOperator() && $token->prev()->isTernaryOperator())
        {
            $token->WhitespaceBefore = $token->prev()->WhitespaceAfter = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
    }
}
