<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken as Token;

/**
 * Remove whitespace after T_OPEN_TAG and T_OPEN_TAG_WITH_ECHO for comparison
 *
 */
final class TrimOpenTags implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $openTags = array_filter(
            $tokens,
            fn(Token $t) =>
                $t->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])
        );

        foreach ($openTags as $t) {
            $t->setText(rtrim($t->text));
        }

        return $tokens;
    }
}
