<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply vertical whitespace to operators
 *
 * Specifically:
 * - If one ternary operator is at the start of a line, add a newline before the
 *   other
 */
final class BreakOperators implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 98;
    }

    public function getTokenTypes(): ?array
    {
        return [T['?']];
    }

    public function processToken(Token $token): void
    {
        if (!$token->IsTernaryOperator ||
                $token->TernaryOperator2 === $token->_next) {
            return;
        }

        // If one ternary operator is at the start of a line, add a newline
        // before the other
        $op1Newline = $token->hasNewlineBefore();
        $op2Newline = $token->TernaryOperator2->hasNewlineBefore();
        if ($op1Newline && !$op2Newline) {
            $token->TernaryOperator2->WhitespaceBefore |= WhitespaceType::LINE;
        } elseif (!$op1Newline && $op2Newline) {
            $token->WhitespaceBefore |= WhitespaceType::LINE;
        }
    }
}
