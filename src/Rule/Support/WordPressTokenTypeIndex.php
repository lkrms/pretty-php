<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Support;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Indexed tokens by type, for use with the WordPress preset
 *
 * @api
 */
class WordPressTokenTypeIndex extends TokenTypeIndex
{
    public static function create(): WordPressTokenTypeIndex
    {
        $instance = (new self())->withLeadingOperators();
        $instance->PreserveBlankAfter[\T_OPEN_BRACE] = true;
        $instance->PreserveBlankBefore[\T_CLOSE_BRACE] = true;
        $instance->PreserveNewlineAfter[\T_CONCAT] = true;

        return $instance;
    }
}
