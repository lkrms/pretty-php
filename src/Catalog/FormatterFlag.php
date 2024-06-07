<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Formatter flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class FormatterFlag extends AbstractEnumeration
{
    /**
     * Collect warnings about non-critical problems detected in formatted code
     */
    public const COLLECT_CODE_PROBLEMS = 1;

    /**
     * Print warnings about non-critical problems detected in formatted code
     */
    public const REPORT_CODE_PROBLEMS = 2;

    /**
     * Enable debug mode
     *
     * Debug mode is enabled automatically if
     * {@see \Salient\Utility\Env::getDebug()} returns `true`.
     */
    public const DEBUG = 4;

    /**
     * In debug mode, render output after processing each pass of each rule
     */
    public const LOG_PROGRESS = 8;
}
