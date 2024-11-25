<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply sensible indentation to switch statements
 */
final class SwitchIndentation implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 600,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_SWITCH => true,
            \T_CASE => true,
            \T_DEFAULT => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_SWITCH) {
                assert($token->NextSibling && $token->NextSibling->NextSibling);
                $token->NextSibling->NextSibling->inner()->forEach(fn(Token $t) => $t->PreIndent++);

                continue;
            }

            if (!$token->inSwitch()) {
                continue;
            }

            /** @var Token */
            $separator = $token->EndStatement;
            $token->Whitespace |= Space::LINE_BEFORE;
            $separator->Whitespace |= Space::NO_BLANK_AFTER | Space::LINE_AFTER | Space::SPACE_AFTER;
            $token->collect($separator)->forEach(fn(Token $t) => $t->Deindent++);
        }
    }
}
