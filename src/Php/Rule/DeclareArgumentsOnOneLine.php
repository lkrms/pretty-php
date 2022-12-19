<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class DeclareArgumentsOnOneLine extends AbstractTokenRule
{
    public function __invoke(Token $token, int $stage): void
    {
        if ($token->is('(') &&
            ($token->prevCode()->isOneOf(T_FN, T_FUNCTION) ||
                $token->prevCode(2)->is(T_FUNCTION))) {
            $mask                                 = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $token->WhitespaceMaskNext           &= $mask;
            $token->ClosedBy->WhitespaceMaskPrev &= $mask;
            $token->inner()->forEach(
                static function (Token $t) use ($mask) {
                    $t->WhitespaceMaskPrev &= $mask;
                    $t->WhitespaceMaskNext &= $mask;
                }
            );
        }
    }
}
