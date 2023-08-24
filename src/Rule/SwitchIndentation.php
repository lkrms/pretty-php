<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply sensible indentation to switch statements
 *
 */
final class SwitchIndentation implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 600;
    }

    public function getTokenTypes(): array
    {
        return [
            T_SWITCH,
            T_CASE,
            T_DEFAULT,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->id === T_SWITCH) {
            $token->nextSibling(2)->inner()->forEach(fn(Token $t) => $t->PreIndent++);

            return;
        }

        if (!$token->is([T_CASE, T_DEFAULT]) ||
                $token->parent()->prevSibling(2)->id !== T_SWITCH ||
                ($separator = $token->nextSiblingOf(T_COLON, T_SEMICOLON, T_CLOSE_TAG))->IsNull) {
            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::LINE;
        $separator->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $separator->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        $token->collect($separator)->forEach(fn(Token $t) => $t->Deindent++);
    }
}
