<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class PlaceAttributes extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->is(T_ATTRIBUTE)) {
            return;
        }

        $token->WhitespaceBefore             |= WhitespaceType::LINE;
        $token->ClosedBy->WhitespaceAfter    |= WhitespaceType::LINE;
        $token->ClosedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
    }
}
