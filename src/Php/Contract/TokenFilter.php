<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;

interface TokenFilter
{
    /**
     * Apply the filter to an array of tokens
     *
     * @template T of Token
     * @param T[] $tokens
     * @return T[]
     */
    public function __invoke(array $tokens): array;
}
