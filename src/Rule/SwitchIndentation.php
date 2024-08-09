<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply sensible indentation to switch statements
 */
final class SwitchIndentation implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 600;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_SWITCH,
            \T_CASE,
            \T_DEFAULT,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_SWITCH) {
                $token->nextSibling(2)->inner()->forEach(fn(Token $t) => $t->PreIndent++);

                continue;
            }

            if (!$token->is([\T_CASE, \T_DEFAULT])
                    || $token->parent()->prevSibling(2)->id !== \T_SWITCH
                    || ($separator = $token->nextSiblingOf(\T_COLON, \T_SEMICOLON, \T_CLOSE_TAG))->IsNull) {
                continue;
            }

            $token->WhitespaceBefore |= WhitespaceType::LINE;
            $separator->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $separator->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $token->collect($separator)->forEach(fn(Token $t) => $t->Deindent++);
        }
    }
}
