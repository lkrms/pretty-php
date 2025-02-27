<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;

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
            self::PROCESS_TOKENS => 204,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->ReturnOrYield;
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
                    && !(
                        $token->Parent->Flags & Flag::STRUCTURAL_BRACE
                        || $token->Parent->id === \T_COLON
                    )
                )
            ) {
                continue;
            }

            if (
                ($prev = $token->skipPrevEmptyStatements()->PrevSibling)
                && $prev->Statement
                && $this->Idx->ReturnOrYield[$prev->Statement->id]
            ) {
                continue;
            }

            $token->applyBlankBefore();
        }
    }
}
