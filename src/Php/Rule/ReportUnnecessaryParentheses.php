<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

class ReportUnnecessaryParentheses implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            '(',
        ];
    }

    public function processToken(Token $token): void
    {
        if (!($token->isStartOfExpression() ||
            (($start = $token->prevCode())->isStartOfExpression() &&
                    $start->isOneOf(...TokenType::HAS_EXPRESSION_WITH_OPTIONAL_PARENTHESES))) ||
                $token->endOfExpression() !== $token->ClosedBy) {
            return;
        }
        $start = $start ?? $token;
        $inner = $token->inner();
        if (!count($inner)) {
            return;
        }
        $first = $inner->first();
        $last  = $inner->last();
        if (!$first->isStartOfExpression() ||
                $first->endOfExpression() !== $last) {
            return;
        }
        $prev = $start->prevCode();
        $next = $token->ClosedBy->nextCode();
        if (!(($prev->isStatementPrecursor() || $prev->isOneOf(...TokenType::OPERATOR_ASSIGNMENT)) &&
                ($prev->ClosedBy === $next || $next->isStatementPrecursor()))) {
            return;
        }
        $this->Formatter->reportProblem('Unnecessary parentheses', $first, $last);
    }
}
