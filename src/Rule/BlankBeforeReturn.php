<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Add blank lines before return statements
 *
 * @api
 */
final class BlankBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 97,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return $idx->Return;
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
     * Blank lines are added before non-consecutive `return`, `yield` and `yield
     * from` statements.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore `yield` and `yield from` in non-statement contexts
            if (
                $token->Statement !== $token || (
                    $token->Parent
                    && !($token->Parent->Flags & TokenFlag::STRUCTURAL_BRACE)
                )
            ) {
                continue;
            }

            // Ignore empty statements
            $prev = $token;
            while (
                $prev->PrevCode
                && $prev->PrevCode->id === \T_SEMICOLON
                && $prev->PrevCode->Statement === $prev->PrevCode
            ) {
                $prev = $prev->PrevCode;
            }

            if (
                $prev->PrevSibling
                && $prev->PrevSibling->Statement
                && $this->Idx->Return[$prev->PrevSibling->Statement->id]
            ) {
                continue;
            }

            $token->applyBlankBefore();
        }
    }
}
