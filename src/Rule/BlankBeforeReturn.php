<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Add a blank line before return and yield statements unless they appear
 * consecutively or at the beginning of a compound statement
 *
 * @api
 */
final class BlankBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
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

    public function processToken(Token $token): void
    {
        if (($prev = $token->PrevSibling->Statement ?? null)
                && $prev->is([\T_RETURN, \T_YIELD, \T_YIELD_FROM])) {
            return;
        }
        $token->applyBlankLineBefore();
    }
}
