<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

/**
 * Remove whitespace inside cast operators
 *
 */
final class TrimInsideCasts implements TokenFilter
{
    public function __invoke(array $tokens): array
    {
        return array_map(
            function (Token $t) {
                if (!$t->is(TokenType::CAST)) {
                    return $t;
                }
                $text    = $t->text;
                $t->text = '(' . trim(substr($t->text, 1, -1)) . ')';
                if ($text !== $t->text) {
                    $t->OriginalText = $t->OriginalText ?: $text;
                }

                return $t;
            },
            $tokens
        );
    }
}
