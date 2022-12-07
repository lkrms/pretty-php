<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class BracePosition extends AbstractTokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$token->isStructuralBrace()) {
            return;
        }

        if ($token->is('{')) {
            $token->WhitespaceBefore |= ($token->isDeclaration() &&
                (($prev = $token->startOfExpression()->prevCode())->isNull() ||
                    $prev->isStatementPrecursor('(', '['))
                    ? WhitespaceType::LINE
                    : WhitespaceType::SPACE);
            $token->WhitespaceAfter    |= WhitespaceType::LINE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;

            return;
        }

        $token->WhitespaceBefore   |= WhitespaceType::LINE;
        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

        $next = $token->nextCode();
        if ($next->isOneOf(T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY) ||
                ($next->is(T_WHILE) && $next->nextSibling(2)->is(';'))) {
            $token->WhitespaceAfter    |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
    }
}
