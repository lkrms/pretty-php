<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Formatter;

/**
 * Base interface for formatter extensions
 */
interface Extension
{
    public function __construct(Formatter $formatter);

    /**
     * Clear the extension's state for a new payload
     *
     * Called once per input file, before formatting begins.
     */
    public function reset(): void;
}
