<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * Add newlines after statement terminators and spaces between `for` loop
 * expressions
 *
 */
final class BreakAfterSeparators implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 80;
    }

    public function getTokenTypes(): array
    {
        return [
            T_SEMICOLON,
            T_COLON,
            T_CLOSE_TAG,
        ];
    }

    public function processToken(Token $token): void
    {
        if ($token->IsCloseTagStatementTerminator) {
            $token->prev()->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

            return;
        }
        if ($token->id === T_SEMICOLON) {
            $parent = $token->parent();
            if ($parent->id === T_OPEN_PARENTHESIS && $parent->prevCode()->id === T_FOR) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                $token->next()->WhitespaceMaskPrev |= WhitespaceType::SPACE;

                return;
            }
            if ($token->startOfStatement()->id === T_HALT_COMPILER) {
                return;
            }
        } elseif (!$token->startsAlternativeSyntax()) {
            return;
        }

        $token->WhitespaceBefore = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }
}
