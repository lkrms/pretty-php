<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Heredoc indentation types
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class HeredocIndent extends AbstractEnumeration
{
    /**
     * Do not indent heredocs
     *
     * ```php
     * <?php
     * function f()
     * {
     *     $a = <<<EOF
     * Content
     * EOF;
     * }
     * ```
     */
    public const NONE = 0;

    /**
     * Apply line indentation to heredocs
     *
     * ```php
     * <?php
     * function f()
     * {
     *     $a = <<<EOF
     *     Content
     *     EOF;
     * }
     * ```
     */
    public const LINE = 1;

    /**
     * Apply hanging indentation to inline heredocs
     *
     * ```php
     * <?php
     * $alpha = <<<EOF
     *     Content
     *     EOF;
     * $bravo =
     *     <<<EOF
     *     Content
     *     EOF;
     * ```
     */
    public const MIXED = 2;

    /**
     * Always apply hanging indentation to heredocs
     *
     * ```php
     * <?php
     * $alpha = <<<EOF
     *     Content
     *     EOF;
     * $bravo =
     *     <<<EOF
     *         Content
     *         EOF;
     * ```
     */
    public const HANGING = 4;
}
