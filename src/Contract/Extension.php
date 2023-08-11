<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Formatter;

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
