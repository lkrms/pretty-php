<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;

interface Rule
{
    public const BEFORE_RENDER = 'beforeRender';

    public function __construct(Formatter $formatter);

    /**
     * Return the priority of a method implemented by the rule
     *
     * Higher priorities (bigger numbers) correspond to later invocation. Return
     * `null` to use the default priority (100).
     *
     * @param string $method One of the rule's public constants, e.g.
     * {@see Rule::BEFORE_RENDER}
     */
    public function getPriority(string $method): ?int;

    public function clear(): void;

    public function beforeRender(): void;
}
