<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddStandardWhitespace implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->isOneOf(...TokenType::ADD_SPACE_AROUND))
        {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter  |= WhitespaceType::SPACE;
        }

        if ($token->isOneOf(...TokenType::ADD_SPACE_BEFORE))
        {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
        }

        if ($token->isOneOf(...TokenType::ADD_SPACE_AFTER))
        {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }

        if ($token->isOpenBracket() ||
            $token->isOneOf(...TokenType::SUPPRESS_SPACE_AFTER))
        {
            $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
        }

        if ($token->isCloseBracket())
        {
            $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        }
    }
}
