<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;

interface Rule
{
    public const BEFORE_RENDER = 'beforeRender';

    public function __construct(Formatter $formatter);

    /**
     * Get the priority of a method implemented by the rule
     *
     * Higher priorities (bigger numbers) correspond to later invocation. To
     * suppress calls to the method, return `null`.
     *
     * @param string $method One of the rule's public constants, e.g.
     * {@see Rule::BEFORE_RENDER}
     */
    public function getPriority(string $method): ?int;

    /**
     * @param Token[] $tokens
     */
    public function beforeRender(array $tokens): void;

    /**
     * Clear state for a new payload
     *
     */
    public function reset(): void;
}
