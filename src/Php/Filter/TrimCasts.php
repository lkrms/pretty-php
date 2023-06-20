<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken as Token;
use Lkrms\Pretty\Php\TokenType;

/**
 * Remove whitespace inside cast operators
 *
 */
final class TrimCasts implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $casts = array_filter(
            $tokens,
            fn(Token $t) => $t->is(TokenType::CAST)
        );

        array_walk(
            $casts,
            function (Token $t) {
                $t->setText('(' . trim(substr($t->text, 1, -1)) . ')');
            }
        );

        return $tokens;
    }
}
