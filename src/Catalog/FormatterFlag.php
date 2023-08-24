<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Formatter flags
 *
 * @api
 *
 * @extends Enumeration<int>
 */
final class FormatterFlag extends Enumeration
{
    /**
     * Print warnings about non-critical problems detected in formatted code
     *
     */
    public const REPORT_CODE_PROBLEMS = 1;

    /**
     * Enable debug mode
     *
     * Debug mode is enabled automatically if {@see \Lkrms\Utility\Env::debug()}
     * returns `true`.
     *
     */
    public const DEBUG = 2;

    /**
     * In debug mode, render output after processing each pass of each rule
     *
     */
    public const LOG_PROGRESS = 4;
}
