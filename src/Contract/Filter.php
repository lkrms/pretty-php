<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token\GenericToken;

interface Filter extends Extension
{
    /**
     * Apply the filter to a list of tokens
     *
     * @template T of GenericToken
     *
     * @param list<T> $tokens
     * @return list<T>
     */
    public function filterTokens(array $tokens): array;
}
