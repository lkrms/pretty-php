<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Apply the WordPress code style
 *
 * Specifically:
 * - Add a space after '!' unless it appears before another '!'
 * - Add a space inside non-empty parentheses
 * - Add a space inside non-empty square brackets unless their first inner token
 *   is a T_CONSTANT_ENCAPSED_STRING
 */
final class WordPress implements TokenRule
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
            T['('],
            T[':'],
            T['['],
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

            case T['(']:
            case T['[']:
                if ($token->id === T['('] && $token->_next->id === T[')']) {
                    return;
                }
                if ($token->id === T['['] && $token->_next->is([T[']'], T_CONSTANT_ENCAPSED_STRING])) {
                    return;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                return;

            case T[':']:
                if (!$token->startsAlternativeSyntax()) {
                    return;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                return;
        }
    }
}
