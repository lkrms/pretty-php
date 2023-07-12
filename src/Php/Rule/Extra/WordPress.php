<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

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
            T_LOGICAL_NOT,
            T_OPEN_PARENTHESIS,
            T_COLON,
            T_OPEN_BRACKET,
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

            case T_OPEN_PARENTHESIS:
            case T_OPEN_BRACKET:
                if ($token->id === T_OPEN_PARENTHESIS && $token->_next->id === T_CLOSE_PARENTHESIS) {
                    return;
                }
                if ($token->id === T_OPEN_BRACKET && $token->_next->is([T_CLOSE_BRACKET, T_CONSTANT_ENCAPSED_STRING])) {
                    return;
                }
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                return;

            case T_COLON:
                if (!$token->startsAlternativeSyntax()) {
                    return;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                return;
        }
    }
}
