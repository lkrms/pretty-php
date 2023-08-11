<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use PhpToken;

/**
 * @template TToken of PhpToken
 */
interface Filter extends Extension
{
    /**
     * Apply the filter to an array of tokens
     *
     * @param TToken[] $tokens
     * @return TToken[]
     */
    public function filterTokens(array $tokens): array;
}
