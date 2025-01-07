<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Salient\Core\Concern\HasMutator;

/**
 * @api
 */
class TokenIndex extends AbstractTokenIndex
{
    use HasMutator;

    protected const FIRST = 0;
    protected const LAST = 1;
    protected const MIXED = 2;

    /** @var self::FIRST|self::LAST|self::MIXED */
    protected int $Operators;

    public function __construct(
        bool $operatorsFirst = false,
        bool $operatorsLast = false
    ) {
        $operators = $operatorsFirst
            ? self::FIRST
            : ($operatorsLast ? self::LAST : self::MIXED);

        if ($operators === self::FIRST) {
            [$before, $after] = $this->getOperatorsFirstIndexes();
        } elseif ($operators === self::LAST) {
            [$before, $after] = $this->getOperatorsLastIndexes();
        }

        $this->AllowNewlineBefore = $before ?? self::DEFAULT_ALLOW_NEWLINE_BEFORE;
        $this->AllowNewlineAfter = $after ?? self::DEFAULT_ALLOW_NEWLINE_AFTER;
        $this->Operators = $operators;
    }

    /**
     * @inheritDoc
     */
    public function withLeadingOperators()
    {
        [$before, $after] = $this->getOperatorsFirstIndexes();
        return $this->with('AllowNewlineBefore', $before)
                    ->with('AllowNewlineAfter', $after)
                    ->with('Operators', self::FIRST);
    }

    /**
     * @return array{array<int,bool>,array<int,bool>}
     */
    private function getOperatorsFirstIndexes(): array
    {
        $both = self::intersect(
            self::DEFAULT_ALLOW_NEWLINE_BEFORE,
            self::DEFAULT_ALLOW_NEWLINE_AFTER,
        );

        $before = self::OPERATOR_COMPARISON
            + self::OPERATOR_LOGICAL
            + self::DEFAULT_ALLOW_NEWLINE_BEFORE;

        $after = self::merge(
            self::diff(
                self::DEFAULT_ALLOW_NEWLINE_AFTER,
                $before,
            ),
            $both
        );

        return [$before, $after];
    }

    /**
     * @inheritDoc
     */
    public function withTrailingOperators()
    {
        [$before, $after] = $this->getOperatorsLastIndexes();
        return $this->with('AllowNewlineBefore', $before)
                    ->with('AllowNewlineAfter', $after)
                    ->with('Operators', self::LAST);
    }

    /**
     * @return array{array<int,bool>,array<int,bool>}
     */
    private function getOperatorsLastIndexes(): array
    {
        $both = self::intersect(
            self::DEFAULT_ALLOW_NEWLINE_BEFORE,
            self::DEFAULT_ALLOW_NEWLINE_AFTER,
        );

        $after = [
            \T_COALESCE => true,
            \T_CONCAT => true,
        ]
            + self::OPERATOR_ARITHMETIC
            + self::OPERATOR_BITWISE
            + self::DEFAULT_ALLOW_NEWLINE_AFTER;

        $before = self::merge(
            self::diff(
                self::DEFAULT_ALLOW_NEWLINE_BEFORE,
                $after,
            ),
            $both
        );

        return [$before, $after];
    }

    /**
     * @inheritDoc
     */
    public function withMixedOperators()
    {
        return $this->with('AllowNewlineBefore', self::DEFAULT_ALLOW_NEWLINE_BEFORE)
                    ->with('AllowNewlineAfter', self::DEFAULT_ALLOW_NEWLINE_AFTER)
                    ->with('Operators', self::MIXED);
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
        switch ($this->Operators) {
            case self::FIRST:
                return $this->withLeadingOperators();
            case self::LAST:
                return $this->withTrailingOperators();
            case self::MIXED:
                return $this->withMixedOperators();
        }
    }
}
