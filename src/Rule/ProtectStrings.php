<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Suppress changes to whitespace inside strings and heredocs
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
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 40;

            default:
                return null;
        }
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
    public static function getRequiresSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Whitespace is suppressed via critical masks applied to siblings in
     * non-constant strings, and to every token between square brackets in those
     * strings.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $next = $token->NextSibling;
            if (!$next || $next->String !== $token) {
                continue;
            }

            /** @var Token */
            $closedBy = $token->StringClosedBy;
            foreach ($next->collectSiblings($closedBy) as $current) {
                $current->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;

                // "$foo[0]" and "$foo[$bar]" fail to parse if there is any
                // whitespace between the brackets
                if ($current->id === \T_OPEN_BRACKET) {
                    /** @var Token */
                    $next = $current->Next;
                    /** @var Token */
                    $closedBy = $current->ClosedBy;
                    foreach ($next->collect($closedBy) as $inner) {
                        $inner->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                    }
                }
            }
        }
    }
}
