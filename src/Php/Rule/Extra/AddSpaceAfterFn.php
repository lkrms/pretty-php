<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddSpaceAfterFn extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if (!$token->is(T_FN)) {
            return;
        }

        $token->WhitespaceBefore  |= WhitespaceType::SPACE;
        $token->WhitespaceAfter   |= WhitespaceType::SPACE;
        $token->WhitespaceMaskNext = WhitespaceType::SPACE;
    }
}
