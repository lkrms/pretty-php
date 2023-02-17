<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;
use const Lkrms\Pretty\Php\T_ID_MAP as T;

class AddSpaceAfterNot implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            T['!'],
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->nextCode()->isUnaryOperator()) {
            return;
        }

        $token->WhitespaceBefore  |= WhitespaceType::SPACE;
        $token->WhitespaceAfter   |= WhitespaceType::SPACE;
        $token->WhitespaceMaskNext = WhitespaceType::SPACE;
    }
}
