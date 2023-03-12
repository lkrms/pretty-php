<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;

/**
 * Remove whitespace after T_OPEN_TAG and T_OPEN_TAG_WITH_ECHO for comparison
 *
 */
final class TrimOpenTags implements Filter
{
    public function __invoke(array $tokens): array
    {
        $openTags = array_filter(
            $tokens,
            fn(Token $t) =>
                $t->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])
        );

        foreach ($openTags as $t) {
            $t->text = rtrim($t->text);
        }

        return $tokens;
    }
}
