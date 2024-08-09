<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Add a blank line before return and yield statements unless they appear
 * consecutively or at the beginning of a compound statement
 *
 * @api
 */
final class BlankBeforeReturn implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 97;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_RETURN,
            \T_YIELD,
            \T_YIELD_FROM,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (($prev = $token->PrevSibling->Statement ?? null)
                    && $prev->is([\T_RETURN, \T_YIELD, \T_YIELD_FROM])) {
                continue;
            }
            $token->applyBlankLineBefore();
        }
    }
}
