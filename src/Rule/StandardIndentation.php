<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;

/**
 * Indent tokens between brackets with inner newlines
 *
 * @api
 */
final class StandardIndentation implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 300,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * Apply the rule to the given tokens
     *
     * `Indent` is copied from open brackets to close brackets, and the `Indent`
     * of tokens between brackets with inner newlines is incremented.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->OpenBracket) {
                $token->Indent = $token->OpenBracket->Indent;
                continue;
            }

            if (!$token->Prev) {
                continue;
            }

            $prev = $token->Prev;
            $token->Indent = $prev->Indent;

            if (
                $prev->CloseBracket
                && $prev->hasNewlineBeforeNextCode()
            ) {
                $token->Indent++;
            }
        }
    }
}
