<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\GenericToken;

/**
 * @api
 */
interface Filter extends Extension
{
    /**
     * Apply the filter to a list of tokens
     *
     * @template T of GenericToken
     *
     * @param non-empty-list<T> $tokens
     * @return list<T>
     */
    public function filterTokens(array $tokens): array;
}
