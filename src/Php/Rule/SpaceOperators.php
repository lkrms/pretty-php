<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply whitespace to operators
 *
 * Specifically:
 * - Suppress whitespace after ampersands related to returning, assigning or
 *   passing by reference
 * - Suppress whitespace between operators in union and intersection types
 * - Suppress whitespace after `?` in nullable types
 * - Suppress whitespace between `++` and `--` and the variables they operate on
 * - Suppress whitespace after unary operators
 * - Collapse ternary operators if there is nothing between `?` and `:`
 *
 * Otherwise, add a space after each operator, and before operators except
 * non-ternary `:`.
 */
final class SpaceOperators implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return TokenType::ALL_OPERATOR;
    }

    public function processToken(Token $token): void
    {
        if ($token->parent()->prevCode()->is(T_DECLARE)) {
            return;
        }

        // Suppress whitespace after ampersands related to returning, assigning
        // or passing by reference
        if ($token->is(TokenType::AMPERSAND) &&
            $token->next()->IsCode &&
            ($token->prevCode()->is(T_FUNCTION) ||      // - `function &getValue()`
                $token->inUnaryContext() ||             // - `[&$variable]`, `$a = &getValue()`
                ($token->next()->is(T_VARIABLE) &&      // - `function getValue(&$param)`, but not
                    $token->inFunctionDeclaration() &&  //   `function getValue($param = $a & $b)`
                    !$token->sinceStartOfStatement()->hasOneOf(T['='])))) {
            $token->WhitespaceBefore  |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace between operators in union and intersection types
        if ($token->is([T['|'], ...TokenType::AMPERSAND]) &&
                $token->inFunctionDeclaration() &&
                !$token->sinceStartOfStatement()->hasOneOf(T['='])) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace after `?` in nullable types
        if ($token->is(T['?']) && !$token->IsTernaryOperator) {
            $token->WhitespaceBefore  |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace between `++` and `--` and the variables they
        // operate on
        if ($token->is(TokenType::OPERATOR_INCREMENT_DECREMENT)) {
            if ($token->prev()->is(T_VARIABLE)) {
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            } elseif ($token->next()->is(T_VARIABLE)) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
            }
        }

        // Suppress whitespace after unary operators
        if ($token->isUnaryOperator() &&
            $token->next()->IsCode &&
            (!$token->nextCode()->isOperator() ||
                $token->nextCode()->isUnaryOperator())) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;

        if ($token->is(T[':']) && !$token->IsTernaryOperator) {
            return;
        }

        // Collapse ternary operators if there is nothing between `?` and `:`
        if ($token->IsTernaryOperator && $token->prev()->IsTernaryOperator) {
            $token->WhitespaceBefore        = WhitespaceType::NONE;
            $token->prev()->WhitespaceAfter = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
    }
}
