<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Formatter;

/**
 * Base interface for filters and rules
 */
interface Extension
{
    public function __construct(Formatter $formatter);

    /**
     * Set the formatter
     */
    public function setFormatter(Formatter $formatter): void;

    /**
     * Initialise and return the object
     *
     * @return $this
     */
    public function prepare();

    /**
     * Clear state for a new payload
     */
    public function reset(): void;
}
