<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Heredoc indentation types
 *
 * @api
 */
interface HeredocIndent
{
    /**
     * Do not indent heredocs
     */
    public const NONE = 0;

    /**
     * Apply line indentation to heredocs
     */
    public const LINE = 1;

    /**
     * Apply hanging indentation to inline heredocs
     */
    public const MIXED = 2;

    /**
     * Always apply hanging indentation to heredocs
     */
    public const HANGING = 3;
}
