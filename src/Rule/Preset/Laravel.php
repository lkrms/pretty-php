<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply Laravel's code style
 *
 * Specifically:
 * - Add a space after '!' unless it appears before another '!'
 * - Suppress horizontal space before and after '.'
 * - Add a space after 'fn' in arrow functions
 */
final class Laravel implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 100;
    }

    public function getTokenTypes(): array
    {
        return [
            T_LOGICAL_NOT,
            T_CONCAT,
            T_FN,
        ];
    }

    public function processToken(Token $token): void
    {
        switch ($token->id) {
            case T_LOGICAL_NOT:
                if (($token->_next->id ?? null) === T_LOGICAL_NOT) {
                    return;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                return;

            case T_CONCAT:
                $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
                return;

            case T_FN:
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                return;
        }
    }
}
