<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Add newlines before and after control structure expressions that break over
 * multiple lines
 *
 * @api
 */
final class StrictExpressions implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 244,
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
     * Newlines are added before and after control structure expressions that
     * break over multiple lines.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            /** @var Token */
            $first = $token->NextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            /** @var Token */
            $last = $first->CloseBracket;
            /** @var Token */
            $beforeLast = $last->Prev;
            if ($first->collect($beforeLast)->hasNewline()) {
                $first->applyWhitespace(Space::LINE_AFTER);
                $last->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }
}
