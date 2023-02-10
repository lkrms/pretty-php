<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            T_RETURN,
            T_YIELD,
            T_YIELD_FROM,
        ];
    }

    public function processToken(Token $token): void
    {
        if (!$token->prevStatementStart()->isOneOf(T_RETURN, T_YIELD, T_YIELD_FROM)) {
            $prev = $token->prev();
            while ($prev->isOneOf(...TokenType::COMMENT) && $prev->hasNewlineBefore()) {
                $prev->PinToCode = true;
                $prev            = $prev->prev();
            }
            $token->WhitespaceBefore |= WhitespaceType::BLANK | WhitespaceType::SPACE;
        }
    }
}
