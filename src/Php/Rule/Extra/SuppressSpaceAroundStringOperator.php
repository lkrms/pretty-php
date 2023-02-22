<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

/**
 * Suppress horizontal space before and after string concatenation operators
 *
 */
final class SuppressSpaceAroundStringOperator implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return TokenType::OPERATOR_STRING;
    }

    public function processToken(Token $token): void
    {
        $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
        $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
    }
}
