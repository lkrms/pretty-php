<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;

/**
 * Remove tokens with no content
 *
 */
final class RemoveEmptyTokens implements Filter
{
    public function __invoke(array $tokens): array
    {
        return array_filter(
            $tokens,
            fn(Token $t) => $t->text !== ''
        );
    }
}
