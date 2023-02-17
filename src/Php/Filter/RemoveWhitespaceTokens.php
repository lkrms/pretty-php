<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

/**
 * Remove whitespace tokens
 *
 */
final class RemoveWhitespaceTokens implements TokenFilter
{
    public function __invoke(array $tokens): array
    {
        return array_filter(
            $tokens,
            fn(Token $t) => !$t->is(TokenType::WHITESPACE)
        );
    }
}
