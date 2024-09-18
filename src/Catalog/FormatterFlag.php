<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Formatter flags
 *
 * @api
 */
interface FormatterFlag
{
    /**
     * Enable debug mode
     */
    public const DEBUG = 1;

    /**
     * In debug mode, render output after processing each pass of each rule
     */
    public const LOG_PROGRESS = 2;

    /**
     * Enable detection of non-critical problems in formatted code
     */
    public const DETECT_PROBLEMS = 4;
}
