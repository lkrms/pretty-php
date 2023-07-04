<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

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
            T['!'],
            T['.'],
            T_FN,
        ];
    }

    public function processToken(Token $token): void
    {
        switch ($token->id) {
            case T['!']:
                if (($token->_next->id ?? null) === T['!']) {
                    return;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                return;

            case T['.']:
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
