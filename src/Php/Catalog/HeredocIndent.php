<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Heredoc indentation
 *
 * @api
 *
 * @extends Enumeration<int>
 */
final class HeredocIndent extends Enumeration
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
