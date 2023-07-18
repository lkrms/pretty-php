<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Detect and report superfluous parentheses
 *
 */
final class ReportUnnecessaryParentheses implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 990;
    }

    public function getTokenTypes(): array
    {
        return [
            T_OPEN_PARENTHESIS,
        ];
    }

    public function processToken(Token $token): void
    {
        if (!$token->isStartOfExpression()) {
            $start = $token->prevCode();
            if (!($start->isStartOfExpression() &&
                    $start->is(TokenType::HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES))) {
                return;
            }
        }
        if ($token->pragmaticEndOfExpression() !== $token->ClosedBy) {
            return;
        }
        $start = $start ?? $token;
        $inner = $token->inner();
        if (!count($inner)) {
            return;
        }
        $first = $inner->first();
        $last = $inner->last();
        if ($first->EndStatement !== $last) {
            return;
        }
        $prev = $start->prevCode();
        $next = $token->ClosedBy->nextCode();
        if (($prev->precedesStatement() ||
                $prev->is([...TokenType::OPERATOR_DOUBLE_ARROW, ...TokenType::OPERATOR_ASSIGNMENT])) &&
            ($prev->ClosedBy === $next ||
                $next->Statement === $next ||
                $next->EndStatement === $next)) {
            $this->Formatter->reportProblem($this, 'Unnecessary parentheses', $first, $last);
        }
    }
}
