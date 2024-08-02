<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;
use Salient\Utility\Env;

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
     * Enable debug mode
     *
     * Debug mode is enabled automatically if {@see Env::getDebug()} returns
     * `true`.
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
