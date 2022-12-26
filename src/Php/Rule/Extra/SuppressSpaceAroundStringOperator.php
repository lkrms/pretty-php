<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class SuppressSpaceAroundStringOperator implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (!$token->isOneOf(...TokenType::OPERATOR_STRING)) {
            return;
        }

        $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
    }
}
