<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;

/**
 * Add a blank line before return and yield statements unless they appear
 * consecutively or at the beginning of a compound statement
 *
 * @api
 */
final class BlankLineBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 97;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_RETURN,
            T_YIELD,
            T_YIELD_FROM,
        ];
    }

    public function processToken(Token $token): void
    {
        if (($prev = $token->_prevSibling->Statement ?? null) &&
                $prev->is([T_RETURN, T_YIELD, T_YIELD_FROM])) {
            return;
        }
        $token->applyBlankLineBefore();
    }
}
