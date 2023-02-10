<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class PlaceAttributes implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            T_ATTRIBUTE,
        ];
    }

    public function processToken(Token $token): void
    {
        $token->WhitespaceBefore             |= WhitespaceType::LINE;
        $token->ClosedBy->WhitespaceAfter    |= WhitespaceType::LINE;
        $token->ClosedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
    }
}
