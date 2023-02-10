<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class SpaceOperators implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return TokenType::ALL_OPERATOR;
    }

    public function processToken(Token $token): void
    {
        if ($token->parent()->prev()->is(T_DECLARE)) {
            return;
        }

        // Suppress whitespace after ampersands related to returning, assigning
        // or passing by reference
        if ($token->isOneOf(...TokenType::AMPERSAND) &&
            ($token->prevCode()->is(T_FUNCTION) ||
                $token->inUnaryContext() ||
                ($token->next()->is(T_VARIABLE) &&
                    $token->inFunctionDeclaration() &&
                    !$token->sinceStartOfStatement()->hasOneOf('=')))) {
            $token->WhitespaceBefore  |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace between types in unions and intersections
        if ($token->isOneOf('|', ...TokenType::AMPERSAND) &&
                $token->inFunctionDeclaration() &&
                !$token->sinceStartOfStatement()->hasOneOf('=')) {
            $token->WhitespaceMaskNext = $token->WhitespaceMaskPrev = WhitespaceType::NONE;

            return;
        }

        if ($token->is('?') && !$token->isTernaryOperator()) {
            $token->WhitespaceBefore  |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        if ($token->isOneOf(...TokenType::OPERATOR_INCREMENT_DECREMENT)) {
            if ($token->prev()->is(T_VARIABLE)) {
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            }
            if ($token->next()->is(T_VARIABLE)) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
            }
        }

        if ($token->isUnaryOperator() && !$token->nextCode()->isOperator()) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;

        if ($token->is(':') && !$token->isTernaryOperator()) {
            return;
        }

        // Collapse ternary operators if there is nothing between `?` and `:`
        if ($token->isTernaryOperator() && $token->prev()->isTernaryOperator()) {
            $token->WhitespaceBefore = $token->prev()->WhitespaceAfter = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
    }
}
