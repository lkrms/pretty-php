<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * If there are newlines between siblings in a control structure expression, add
 * newlines before and after it
 *
 * @api
 */
final class SemiStrictExpressions implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 98,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
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

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            /** @var Token */
            $first = $token->NextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            if ($first->children()->tokenHasNewlineAfter(true)) {
                /** @var Token */
                $last = $first->ClosedBy;
                $first->applyWhitespace(Space::LINE_AFTER);
                $last->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }
}
