<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Suppress changes to whitespace inside strings and heredocs
 *
 * @api
 */
final class ProtectStrings implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 40;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_DOUBLE_QUOTE,
            \T_START_HEREDOC,
            \T_BACKTICK,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (!$token->Next || $token->Next->String !== $token) {
                continue;
            }

            foreach ($token->NextSibling->collectSiblings($token->StringClosedBy) as $current) {
                $current->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                // "$foo[0]" and "$foo[$bar]" fail to parse if there is any
                // whitespace between the brackets
                if ($current->id === \T_OPEN_BRACKET) {
                    foreach ($current->Next->collect($current->ClosedBy) as $inner) {
                        $inner->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                    }
                }
            }
        }
    }
}
