<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule\Extra;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Add a space after 'fn' in arrow functions
 *
 */
final class AddSpaceAfterFn implements TokenRule
{
    use TokenRuleTrait;

    public function getTokenTypes(): ?array
    {
        return [
            T_FN,
        ];
    }

    public function processToken(Token $token): void
    {
        $token->WhitespaceAfter    |= WhitespaceType::SPACE;
        $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
    }
}
