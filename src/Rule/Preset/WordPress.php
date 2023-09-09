<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply the WordPress code style
 *
 * Specifically:
 * - Add a space before alternative syntax ':' operators
 * - Suppress horizontal space after `exit` and `die`
 * - Add a space after '!' unless it appears before another '!'
 * - Add a space inside non-empty parentheses
 * - Add a space inside non-empty square brackets unless their first inner token
 *   is a T_CONSTANT_ENCAPSED_STRING
 */
final class WordPress implements TokenRule
{
    use TokenRuleTrait;

    private bool $DocCommentUnpinned = false;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 100;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_DOC_COMMENT,
            T_COLON,
            T_EXIT,
            T_LOGICAL_NOT,
            T_OPEN_BRACE,
            T_CLOSE_BRACE,
            T_OPEN_BRACKET,
            T_OPEN_PARENTHESIS,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->id === T_DOC_COMMENT) {
            if (!$this->DocCommentUnpinned) {
                $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
                $this->DocCommentUnpinned = true;
            }
            return;
        }

        if ($token->id === T_COLON) {
            if (!$token->startsAlternativeSyntax()) {
                return;
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceMaskPrev |= WhitespaceType::SPACE;
            return;
        }

        if ($token->id === T_EXIT) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            return;
        }

        if ($token->id === T_LOGICAL_NOT) {
            if ($token->_next->id === T_LOGICAL_NOT) {
                return;
            }
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
            return;
        }

        if ($token->id === T_OPEN_BRACE) {
            $token->WhitespaceMaskNext |= WhitespaceType::BLANK;
            return;
        }

        if ($token->id === T_CLOSE_BRACE) {
            $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
            return;
        }

        // All that remains is T_OPEN_BRACKET and T_OPEN_PARENTHESIS
        if ($token->ClosedBy === $token->_next ||
            ($token->id === T_OPEN_BRACKET &&
                ($token->String ||
                    ($token->_next->_next === $token->ClosedBy &&
                        $token->_next->id !== T_VARIABLE)))) {
            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::SPACE;
        $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
        $token->ClosedBy->WhitespaceBefore |= WhitespaceType::SPACE;
        $token->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::SPACE;
    }

    public function reset(): void
    {
        $this->DocCommentUnpinned = false;
    }
}
