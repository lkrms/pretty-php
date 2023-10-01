<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply whitespace to operators
 *
 * - Suppress whitespace after ampersands related to passing, assigning and
 *   returning by reference
 * - Suppress whitespace around operators in union, intersection and DNF types
 * - Suppress whitespace around exception delimiters in `catch` blocks (unless
 *   in strict PSR-12 mode)
 * - Suppress whitespace after `?` in nullable types
 * - Suppress whitespace between `++` and `--` and the variables they operate on
 * - Suppress whitespace after unary operators
 * - Collapse ternary operators with nothing between `?` and `:`
 *
 * Otherwise, add a space after each operator, and before operators except
 * non-ternary `:`.
 */
final class OperatorSpacing implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 80;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_DOLLAR,
            ...TokenType::OPERATOR_ALL,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->Parent &&
                    $token->Parent->_prevCode &&
                    $token->Parent->_prevCode->id === T_DECLARE) {
                continue;
            }

            // Suppress whitespace after ampersands related to passing,
            // assigning and returning by reference
            if ($this->TypeIndex->Ampersand[$token->id] &&
                $token->_next->IsCode &&
                // `function &getValue()`
                (($token->_prevCode && $token->_prevCode->id === T_FUNCTION) ||
                    // `[&$variable]`, `$a = &getValue()`
                    $token->inUnaryContext() ||
                    // `function foo(&$bar)`, `function foo($bar, &...$baz)`
                    (($token->_next->id === T_VARIABLE ||
                            $token->_next->id === T_ELLIPSIS) &&
                        $token->inParameterList() &&
                        // Not `function getValue($param = $a & $b)`
                        !$token->sinceStartOfStatement()->hasOneOf(T_VARIABLE)))) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace around operators in union, intersection and
            // DNF types
            if ($this->TypeIndex->TypeDelimiter[$token->id] &&
                (($inTypeContext = $this->inTypeContext($token)) ||
                    ($token->id === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG &&
                        $token->Parent &&
                        $token->Parent->id === T_OPEN_PARENTHESIS &&
                        (($token->Parent->_prevCode &&
                                $token->Parent->_prevCode->id === T_OR) ||
                            $token->Parent->ClosedBy->_nextCode->id === T_OR) &&
                        $this->inTypeContext($token->Parent)))) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;

                if ($inTypeContext) {
                    continue;
                }

                // Add a leading space to DNF types with opening parentheses
                // (e.g. `(A&B)|null`)
                $parent = $token->Parent;
                if (!$parent->_prevCode || $parent->_prevCode->id !== T_OR) {
                    $parent->WhitespaceBefore |= WhitespaceType::SPACE;
                }
                continue;
            }

            // Suppress whitespace around exception delimiters in `catch` blocks
            // (unless in strict PSR-12 mode)
            if ($token->id === T_OR &&
                    $token->Parent &&
                    $token->Parent->_prevCode &&
                    $token->Parent->_prevCode->id === T_CATCH &&
                    !$this->Formatter->Psr12Compliance) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace after `?` in nullable types
            if ($token->id === T_QUESTION && !$token->IsTernaryOperator) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                continue;
            }

            // Suppress whitespace between `++` and `--` and the variables they
            // operate on
            if ($token->id === T_INC ||
                    $token->id === T_DEC) {
                if ($token->_prev && $token->_prev->id === T_VARIABLE) {
                    $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                } elseif ($token->_next && $token->_next->id === T_VARIABLE) {
                    $token->WhitespaceMaskNext = WhitespaceType::NONE;
                }
            }

            // Suppress whitespace after unary operators
            if ($token->isUnaryOperator() &&
                $token->_next &&
                $token->_next->IsCode &&
                (!$token->_next->isOperator() ||
                    $token->_next->isUnaryOperator())) {
                $token->WhitespaceMaskNext = WhitespaceType::NONE;

                continue;
            }

            $token->WhitespaceAfter |= WhitespaceType::SPACE;

            if ($token->id === T_COLON && !$token->IsTernaryOperator) {
                continue;
            }

            // Collapse ternary operators with nothing between `?` and `:`
            if ($token->IsTernaryOperator &&
                    $token->TernaryOperator1 === $token->_prev) {
                $token->WhitespaceBefore = WhitespaceType::NONE;
                $token->_prev->WhitespaceAfter = WhitespaceType::NONE;

                continue;
            }

            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        }
    }

    /**
     * True if the token is part of a declaration (i.e. a property type or
     * function return type), parameter type, or arrow function return type
     */
    private function inTypeContext(Token $token): bool
    {
        return $token->isDeclaration() ||
            ($token->inParameterList() &&
                !$token->sinceStartOfStatement()->hasOneOf(T_VARIABLE)) ||
            (($prev = $token->prevCodeWhile(...TokenType::VALUE_TYPE)->last()) &&
                ($prev = $prev->prevCode())->id === T_COLON &&
                $prev->prevSibling(2)->id === T_FN);
    }
}
