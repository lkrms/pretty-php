<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken as Token;

/**
 * Remove whitespace tokens
 *
 */
final class RemoveWhitespace implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        return array_filter(
            $tokens,
            fn(Token $t) => $t->id !== T_WHITESPACE
        );
    }
}
