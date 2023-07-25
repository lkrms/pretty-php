<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Support;

class WordPressTokenTypeIndex extends TokenTypeIndex
{
    protected function __construct()
    {
        parent::__construct();
    }

    public static function create(): WordPressTokenTypeIndex
    {
        $instance = (new self())->withLeadingOperators();
        $instance->PreserveBlankAfter[T_OPEN_BRACE] = true;
        $instance->PreserveBlankBefore[T_CLOSE_BRACE] = true;
        $instance->PreserveNewlineAfter[T_CONCAT] = true;

        return $instance;
    }
}
