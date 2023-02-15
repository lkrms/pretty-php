<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class PreserveOneLineStatements implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO) && $token->CloseTag) {
            $this->maybePreserveOneLine($token, $token->CloseTag);
        } elseif ($token->isCode() && $token->isStartOfExpression()) {
            $this->maybePreserveOneLine($token, $token->pragmaticEndOfExpression());
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
                      function (Token $t) use ($mask) { $t->WhitespaceMaskPrev &= $mask; $t->WhitespaceMaskNext &= $mask; }
                  );
            $start->WhitespaceMaskNext &= $mask;
            $end->WhitespaceMaskPrev   &= $mask;
        }
    }
}
