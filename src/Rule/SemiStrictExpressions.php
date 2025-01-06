<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Add newlines before and after control structure expressions with newlines
 * between siblings
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
            self::PROCESS_TOKENS => 246,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return $idx->HasExpression;
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
     * Newlines are added before and after control structure expressions with
     * newlines between siblings.
     *
     * > Unlike `StrictExpressions`, this rule does not apply leading and
     * > trailing newlines to expressions that would not break over multiple
     * > lines if tokens between brackets were removed.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            /** @var Token */
            $first = $token->NextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            if ($first->children()->pop()->tokenHasNewlineAfter(true)) {
                /** @var Token */
                $last = $first->CloseBracket;
                $first->applyWhitespace(Space::LINE_AFTER);
                $last->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }
}
