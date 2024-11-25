<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
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
        return [
            self::PROCESS_TOKENS => 98,
        ][$method] ?? null;
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

    public static function needsSortedTokens(): bool
    {
        return false;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            /** @var Token */
            $first = $token->NextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            /** @var Token */
            $last = $first->ClosedBy;
            if ($first->collect($last)->hasNewline()) {
                $first->applyWhitespace(Space::LINE_AFTER);
                $last->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }
}
