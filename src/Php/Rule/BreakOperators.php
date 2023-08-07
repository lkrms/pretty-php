<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Apply vertical whitespace to operators
 *
 * Specifically:
 * - If an object operator (`->` or `?->`) is at the start of a line, add a
 *   newline before other object operators in the same chain
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

    public function getTokenTypes(): array
    {
        return [
            T_QUESTION,
            ...TokenType::CHAIN,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->id === T_QUESTION) {
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

            return;
        }

        if ($token !== $token->ChainOpenedBy) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...TokenType::CHAIN_PART)
                       ->filter(fn(Token $t) => $this->TypeIndex->Chain[$t->id]);

        // If an object operator (`->` or `?->`) is at the start of a line,
        // add a newline before other object operators in the same chain
        if ($chain->count() < 2 ||
                !$chain->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        $chain->shift();
        $chain->addWhitespaceBefore(WhitespaceType::LINE);

        return;
    }
}
