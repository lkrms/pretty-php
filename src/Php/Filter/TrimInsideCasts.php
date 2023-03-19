<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

/**
 * Remove whitespace inside cast operators
 *
 */
final class TrimInsideCasts implements Filter
{
    use FilterTrait;

    public function __invoke(array $tokens): array
    {
        $casts = array_filter(
            $tokens,
            fn(Token $t) => $t->is(TokenType::CAST)
        );

        array_walk(
            $casts,
            function (Token $t) {
                $text    = $t->text;
                $t->text = '(' . trim(substr($t->text, 1, -1)) . ')';
                if ($text !== $t->text) {
                    $t->OriginalText = $t->OriginalText ?: $text;
                }
            }
        );

        return $tokens;
    }
}
