<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token\Token;

interface Filter extends Extension
{
    /**
     * Apply the filter to an array of tokens
     *
     * @param Token[] $tokens
     * @return Token[]
     */
    public function filterTokens(array $tokens): array;
}
