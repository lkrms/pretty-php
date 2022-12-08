<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class PreserveOneLineStatements extends AbstractTokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->isStartOfExpression()) {
            return;
        }

        $end = $token->endOfExpression();
        if ($token->Line === $end->Line && $token !== $end) {
            $mask = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $token->next()
                ->collect($end->prev())
                // Because why not test the rule in the rule itself?
                ->withEach(
                    function (Token $t) use ($mask) {$t->WhitespaceMaskPrev &= $mask; $t->WhitespaceMaskNext &= $mask;}
                );
            $token->WhitespaceMaskNext &= $mask;
            $end->WhitespaceMaskPrev   &= $mask;
        }
    }
}
