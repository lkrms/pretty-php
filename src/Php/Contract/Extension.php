<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;

interface Extension
{
    public function __construct(Formatter $formatter);

    /**
     * Set the formatter
     *
     */
    public function setFormatter(Formatter $formatter): void;

    /**
     * Clear state for a new payload
     *
     */
    public function reset(): void;
}
