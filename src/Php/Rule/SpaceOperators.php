<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Apply horizontal whitespace to operators
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

    public function getPriority(string $method): ?int
    {
        return 80;
    }

    public function getTokenTypes(): array
    {
        return [
            T_DOLLAR,
            ...TokenType::OPERATOR_ALL,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->parent()->prevCode()->id === T_DECLARE) {
            return;
        }

        // Suppress whitespace after ampersands related to returning, assigning
        // or passing by reference
        if ($token->is(TokenType::AMPERSAND) &&
            $token->next()->IsCode &&
            // `function &getValue()`
            ($token->prevCode()->id === T_FUNCTION ||
                // `[&$variable]`, `$a = &getValue()`
                $token->inUnaryContext() ||
                // `function getValue(&$param)`
                ($token->next()->id === T_VARIABLE &&
                    $token->inFunctionDeclaration() &&
                    // Not `function getValue($param = $a & $b)`
                    !$token->sinceStartOfStatement()->hasOneOf(T_EQUAL)))) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace between operators in union and intersection types
        if (($token->is([T_OR, ...TokenType::AMPERSAND]) &&
            ($token->isDeclaration() ||
                ($token->inFunctionDeclaration() &&
                    !$token->sinceStartOfStatement()->hasOneOf(T_EQUAL)) ||
                (($prev = $token->prevCodeWhile(...TokenType::VALUE_TYPE)->last()) &&
                    ($prev = $prev->prevCode())->id === T_COLON &&
                    $prev->prevSibling(2)->id === T_FN))) ||
            ($token->id === T_OR &&
                ($prev = $token->prevCodeWhile(T_OR, ...TokenType::DECLARATION_TYPE)->last()) &&
                ($prev = $prev->prevCode())->id === T_OPEN_PARENTHESIS &&
                $prev->prevCode()->id === T_CATCH)) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace after `?` in nullable types
        if ($token->id === T_QUESTION && !$token->IsTernaryOperator) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;

            return;
        }

        // Suppress whitespace between `++` and `--` and the variables they
        // operate on
        if ($token->is(TokenType::OPERATOR_INCREMENT_DECREMENT)) {
            if ($token->prev()->id === T_VARIABLE) {
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            } elseif ($token->next()->id === T_VARIABLE) {
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

        if ($token->id === T_COLON && !$token->IsTernaryOperator) {
            return;
        }

        // Collapse ternary operators if there is nothing between `?` and `:`
        if ($token->IsTernaryOperator && $token->TernaryOperator1 === $token->_prev) {
            $token->WhitespaceBefore = WhitespaceType::NONE;
            $token->_prev->WhitespaceAfter = WhitespaceType::NONE;

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::SPACE;
    }
}
