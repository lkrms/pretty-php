<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply sensible indentation to switch statements
 *
 */
final class SwitchPosition implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 600;
    }

    public function getTokenTypes(): ?array
    {
        return [
            T_SWITCH,
            T_CASE,
            T_DEFAULT,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->is(T_SWITCH)) {
            $token->nextSibling(2)->inner()->forEach(fn(Token $t) => $t->PreIndent++);

            return;
        }

        if (!$token->is([T_CASE, T_DEFAULT]) ||
                !$token->parent()->prevSibling(2)->is(T_SWITCH) ||
                ($separator = $token->nextSiblingOf(T[':'], T[';'], T_CLOSE_TAG))->IsNull) {
            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::LINE;
        $separator->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $separator->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        $token->collect($separator)->forEach(fn(Token $t) => $t->Deindent++);
    }
}
