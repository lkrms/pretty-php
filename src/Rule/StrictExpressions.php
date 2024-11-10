<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * If a control structure expression breaks over multiple lines, add newlines
 * before and after it
 *
 * Necessary for PSR-12 compliance.
 *
 * @api
 */
final class StrictExpressions implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 98;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_IF => true,
            \T_ELSEIF => true,
            \T_SWITCH => true,
            \T_WHILE => true,
            \T_FOR => true,
            \T_FOREACH => true,
        ];
    }

    public static function getRequiresSortedTokens(): bool
    {
        return false;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $first = $token->NextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            $last = $first->ClosedBy;
            if ($first->collect($last)->hasNewline()) {
                $first->WhitespaceAfter |= WhitespaceType::LINE;
                $first->WhitespaceMaskNext |= WhitespaceType::LINE;
                $first->NextCode->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $last->WhitespaceBefore |= WhitespaceType::LINE;
                $last->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $last->PrevCode->WhitespaceMaskNext |= WhitespaceType::LINE;
            }
        }
    }
}
