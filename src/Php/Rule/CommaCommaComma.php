<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class CommaCommaComma implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            ',',
        ];
    }

    public function processToken(Token $token): void
    {
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter   |= WhitespaceType::SPACE;
    }
}
