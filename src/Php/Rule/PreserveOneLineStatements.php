<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class PreserveOneLineStatements extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO) && $token->ClosedBy) {
            $this->maybePreserveOneLine($token, $token->ClosedBy);
        } elseif ($token->isCode() && $token->isStartOfExpression()) {
            $this->maybePreserveOneLine($token, $token->endOfExpression());
        }
    }

    private function maybePreserveOneLine(Token $start, Token $end): void
    {
        if ($start->Line === $end->Line && $start !== $end) {
            $mask = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $start->next()
                  ->collect($end->prev())
                  // Because why not test the rule in the rule itself?
                  ->forEach(
                      function (Token $t) use ($mask) {$t->WhitespaceMaskPrev &= $mask; $t->WhitespaceMaskNext &= $mask;}
                  );
            $start->WhitespaceMaskNext &= $mask;
            $end->WhitespaceMaskPrev   &= $mask;
        }
    }
}
