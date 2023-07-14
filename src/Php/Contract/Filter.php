<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\NavigableToken;

interface Filter
{
    public function __construct(Formatter $formatter);

    /**
     * Apply the filter to an array of tokens
     *
     * @template TToken of NavigableToken
     * @param TToken[] $tokens
     * @return TToken[]
     */
    public function filterTokens(array $tokens): array;

    /**
     * Clear state for a new payload
     *
     */
    public function reset(): void;
}
