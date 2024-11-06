<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Support;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Salient\Core\Concern\HasMutator;

/**
 * @internal
 */
final class WordPressTokenTypeIndex extends TokenTypeIndex
{
    use HasMutator;

    /** @var array<int,bool> */
    private static array $DefaultAllowNewlineBefore;
    /** @var array<int,bool> */
    private static array $DefaultAllowNewlineAfter;

    public function __construct()
    {
        parent::__construct(true);

        $this->PreserveBlankAfter[\T_OPEN_BRACE] = true;
        $this->PreserveBlankBefore[\T_CLOSE_BRACE] = true;
        $this->PreserveNewlineAfter[\T_CONCAT] = true;
        $this->PreserveNewlineBefore[\T_CLOSE_BRACE] = true;

        self::$DefaultAllowNewlineBefore ??= $this->PreserveNewlineBefore;
        self::$DefaultAllowNewlineAfter ??= $this->PreserveNewlineAfter;
    }

    public function withLeadingOperators()
    {
        return $this;
    }

    public function withTrailingOperators()
    {
        return $this;
    }

    public function withMixedOperators()
    {
        return $this;
    }

    public function withPreserveNewline()
    {
        return $this->with('PreserveNewlineBefore', self::$DefaultAllowNewlineBefore)
                    ->with('PreserveNewlineAfter', self::$DefaultAllowNewlineAfter)
                    ->with('Operators', self::FIRST);
    }
}
