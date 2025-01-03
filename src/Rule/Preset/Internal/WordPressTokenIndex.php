<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset\Internal;

use Lkrms\PrettyPHP\TokenIndex;
use Salient\Core\Concern\HasMutator;

/**
 * @internal
 */
final class WordPressTokenIndex extends TokenIndex
{
    use HasMutator;

    /** @var array<int,bool> */
    private static array $DefaultAllowNewlineBefore;
    /** @var array<int,bool> */
    private static array $DefaultAllowNewlineAfter;

    public function __construct()
    {
        parent::__construct(true);

        $this->AllowBlankBefore[\T_CLOSE_BRACE] = true;
        $this->AllowBlankAfter[\T_OPEN_BRACE] = true;
        $this->AllowNewlineBefore[\T_CLOSE_BRACE] = true;
        $this->AllowNewlineAfter[\T_OPEN_BRACE] = true;
        $this->AllowNewlineAfter[\T_CONCAT] = true;

        self::$DefaultAllowNewlineBefore ??= $this->AllowNewlineBefore;
        self::$DefaultAllowNewlineAfter ??= $this->AllowNewlineAfter;
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

    public function withPreserveNewline()
    {
        return $this->with('AllowNewlineBefore', self::$DefaultAllowNewlineBefore)
                    ->with('AllowNewlineAfter', self::$DefaultAllowNewlineAfter)
                    ->with('Operators', self::FIRST);
    }
}
