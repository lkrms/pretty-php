<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AddBlankLineBeforeReturn implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->is(T_RETURN) &&
            !($token->prev()->isOneOf(...TokenType::COMMENT) && $token->prev()->hasNewlineBefore())) {
            $token->WhitespaceBefore |= WhitespaceType::BLANK;
        }
    }
}