<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddEssentialWhitespace implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->hasNewlineAfter() &&
            $token->isOneLineComment() && !$token->next()->is(T_CLOSE_TAG))
        {
            $token->WhitespaceAfter |= WhitespaceType::LINE;
        }
        elseif (!$token->hasWhitespaceAfter() && preg_match(
            '/^[a-zA-Z0-9_\x80-\xff]{2}$/',
            substr($token->Code, -1) . substr($token->next()->Code, 0, 1)
        ))
        {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
        }
    }

}
