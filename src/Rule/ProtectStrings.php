<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

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
    public static function getTokens(TokenIndex $idx): array
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
     *
     * The latter is necessary because strings like `"$foo[0]"` and
     * `"$foo[$bar]"` are unparseable if there is any whitespace between the
     * brackets.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $next = $token->NextSibling;
            if (!$next || $next->String !== $token) {
                continue;
            }

            $closedBy = $token->Data[TokenData::STRING_CLOSED_BY];
            foreach ($next->withNextSiblings($closedBy) as $current) {
                $current->Whitespace |= Space::CRITICAL_NONE_BEFORE;
                if ($current->id === \T_OPEN_BRACKET) {
                    /** @var Token */
                    $next = $current->Next;
                    /** @var Token */
                    $closedBy = $current->CloseBracket;
                    foreach ($next->collect($closedBy) as $inner) {
                        $inner->Whitespace |= Space::CRITICAL_NONE_BEFORE;
                    }
                }
            }
        }
    }
}
