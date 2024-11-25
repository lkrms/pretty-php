<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Suppress changes to whitespace in strings and heredocs
 *
 * @api
 */
final class ProtectStrings implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 40,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_DOUBLE_QUOTE => true,
            \T_START_HEREDOC => true,
            \T_BACKTICK => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Changes to whitespace in non-constant strings are suppressed for:
     *
     * - nested siblings
     * - every descendant of square brackets that are nested siblings
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $next = $token->NextSibling;
            if (!$next || $next->String !== $token) {
                continue;
            }

            $closedBy = $token->Data[TokenData::STRING_CLOSED_BY];
            foreach ($next->collectSiblings($closedBy) as $current) {
                $current->Whitespace |= Space::CRITICAL_NONE_BEFORE;

                // "$foo[0]" and "$foo[$bar]" fail to parse if there is any
                // whitespace between the brackets
                if ($current->id === \T_OPEN_BRACKET) {
                    /** @var Token */
                    $next = $current->Next;
                    /** @var Token */
                    $closedBy = $current->ClosedBy;
                    foreach ($next->collect($closedBy) as $inner) {
                        $inner->Whitespace |= Space::CRITICAL_NONE_BEFORE;
                    }
                }
            }
        }
    }
}
