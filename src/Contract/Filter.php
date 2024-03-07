<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use PhpToken;

interface Filter extends Extension
{
    /**
     * Apply the filter to an array of tokens
     *
     * @template T of PhpToken
     *
     * @param T[] $tokens
     * @return T[]
     */
    public function filterTokens(array $tokens): array;
}
