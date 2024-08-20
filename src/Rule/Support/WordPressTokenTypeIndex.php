<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Support;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * @internal
 */
final class WordPressTokenTypeIndex extends TokenTypeIndex
{
    public function __construct()
    {
        parent::__construct();

        $this->applyLeadingOperators();

        $this->PreserveBlankAfter[\T_OPEN_BRACE] = true;
        $this->PreserveBlankBefore[\T_CLOSE_BRACE] = true;
        $this->PreserveNewlineAfter[\T_CONCAT] = true;
    }
}
