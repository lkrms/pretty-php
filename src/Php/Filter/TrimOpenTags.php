<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Token;

/**
 * Remove whitespace after T_OPEN_TAG and T_OPEN_TAG_WITH_ECHO for comparison
 *
 */
final class TrimOpenTags implements TokenFilter
{
    public function __invoke(array $tokens): array
    {
        return array_map(
            function (Token $t) {
                if (!$t->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                    return $t;
                }
                $t->text = rtrim($t->text);

                return $t;
            },
            $tokens
        );
    }
}
