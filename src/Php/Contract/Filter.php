<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;

interface Filter
{
    /**
     * Apply the filter to an array of tokens
     *
     * @template T0 of Token
     * @param T0[] $tokens
     * @return T0[]
     */
    public function filterTokens(array $tokens): array;

    /**
     * Close resources and remove circular references
     *
     */
    public function destroy(): void;
}
