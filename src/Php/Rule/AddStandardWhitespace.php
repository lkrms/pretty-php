<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddStandardWhitespace implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->isOneOf(T_STRING, T_VARIABLE))
        {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        }

        if ($token->isOpenBracket() || $token->isOneOf(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_ELLIPSIS))
        {
            $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
        }

        if ($token->isCloseBracket())
        {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        }
    }
}
