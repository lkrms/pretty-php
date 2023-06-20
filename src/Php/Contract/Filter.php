<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\NavigableToken;

interface Filter
{
    /**
     * Apply the filter to an array of tokens
     *
     * @template TToken of NavigableToken
     * @param TToken[] $tokens
     * @return TToken[]
     */
    public function filterTokens(array $tokens): array;

    /**
     * Close resources and remove circular references
     *
     */
    public function destroy(): void;
}
