<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Formatter;

/**
 * @api
 */
interface Extension
{
    public function __construct(Formatter $formatter);

    /**
     * Initialise the extension
     *
     * Called once per instance, before {@see Extension::reset()} is first
     * called.
     */
    public function boot(): void;

    /**
     * Clear the extension's state for a new payload
     *
     * Called once per input file, before formatting begins.
     */
    public function reset(): void;
}
