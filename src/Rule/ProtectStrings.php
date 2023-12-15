<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;

/**
 * Suppress changes to whitespace inside strings and heredocs
 *
 * @api
 */
final class ProtectStrings implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 40;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
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
            if (!$token->_next || $token->_next->String !== $token) {
                continue;
            }

            foreach ($token->_nextSibling->collectSiblings($token->StringClosedBy) as $current) {
                $current->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                // "$foo[0]" and "$foo[$bar]" fail to parse if there is any
                // whitespace between the brackets
                if ($current->id === \T_OPEN_BRACKET) {
                    foreach ($current->_next->collect($current->ClosedBy) as $inner) {
                        $inner->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                    }
                }
            }
        }
    }
}
