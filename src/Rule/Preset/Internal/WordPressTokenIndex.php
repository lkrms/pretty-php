<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset\Internal;

use Lkrms\PrettyPHP\AbstractTokenIndex;
use Salient\Core\Concern\HasMutator;

/**
 * @internal
 */
final class WordPressTokenIndex extends AbstractTokenIndex
{
    use HasMutator;

    private self $Original;

    public function __construct()
    {
        [$before, $after] = self::getOperatorsFirstIndexes();

        $this->AllowBlankBefore[\T_CLOSE_BRACE] = true;
        $this->AllowBlankAfter[\T_OPEN_BRACE] = true;
        $before[\T_CLOSE_BRACE] = true;
        $after[\T_OPEN_BRACE] = true;
        $after[\T_CONCAT] = true;

        $this->AllowNewlineBefore = $before;
        $this->AllowNewlineAfter = $after;
        $this->Original = $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withLeadingOperators()
    {
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withTrailingOperators()
    {
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function withMixedOperators()
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutPreserveNewline()
    {
        return $this->with('AllowNewlineBefore', $this->AllowBlankBefore)
                    ->with('AllowNewlineAfter', $this->AllowBlankAfter);
    }

    /**
     * @inheritDoc
     */
    public function withPreserveNewline()
    {
        return $this->Original;
    }
}
