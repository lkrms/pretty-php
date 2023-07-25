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
     * $array = [
     *     <<<EOF
     * Fugiat magna laborum ut occaecat sit nostrud non eiusmod laboris nisi.
     * EOF
     * ];
     * ```
     */
    public const NONE = 0;

    /**
     * Apply line indentation to heredocs
     *
     * ```php
     * $getString = function () {
     *     return <<<EOF
     *     Incididunt in sint sit aliqua pariatur ad.
     *     EOF;
     * };
     * ```
     */
    public const LINE = 1;

    /**
     * Apply hanging indentation to inline heredocs
     *
     * ```php
     * $string1 = <<<EOF
     *     Enim Lorem nostrud pariatur aliqua.
     *     EOF;
     * $string2 =
     *     <<<EOF
     *     Aliquip mollit elit consectetur nulla laborum minim amet.
     *     EOF;
     * ```
     */
    public const MIXED = 2;

    /**
     * Always apply hanging indentation to heredocs
     *
     * ```php
     * $string1 = <<<EOF
     *     Enim Lorem nostrud pariatur aliqua.
     *     EOF;
     * $string2 =
     *     <<<EOF
     *         Aliquip mollit elit consectetur nulla laborum minim amet.
     *         EOF;
     * ```
     */
    public const HANGING = 4;
}
