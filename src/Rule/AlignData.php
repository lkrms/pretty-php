<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\BlockRuleTrait;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Token;

/**
 * Align consecutive assignment operators, "=>" delimiters in array syntax, and
 * "=>" delimiters in match expressions
 *
 * @api
 */
final class AlignData implements BlockRule
{
    use BlockRuleTrait;

    private const TYPE_ASSIGNMENT = 0;
    private const TYPE_DOUBLE_ARROW = 1;

    private bool $ListRuleEnabled;
    /** @var array<int,int|null> */
    private array $MaxPaddingByType;
    /** @var array<int,int|null> */
    private array $MaxColumnByType;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_BLOCK => 340,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->ListRuleEnabled = $this->Formatter->Enabled[AlignLists::class]
            ?? $this->Formatter->Enabled[StrictLists::class]
            ?? false;
        $this->MaxPaddingByType = [
            self::TYPE_ASSIGNMENT => $this->Formatter->MaxAssignmentPadding,
            self::TYPE_DOUBLE_ARROW => null,
        ];
        $this->MaxColumnByType = [
            self::TYPE_ASSIGNMENT => null,
            self::TYPE_DOUBLE_ARROW => $this->Formatter->MaxDoubleArrowColumn,
        ];
    }

    /**
     * Apply the rule to the given code block
     *
     * When they appear in the same scope, a callback is registered to align
     * consecutive:
     *
     * - assignment operators
     * - `=>` delimiters in array syntax (except as noted below)
     * - `=>` delimiters in `match` expressions
     *
     * If the open bracket of an array is not followed by a newline and neither
     * `AlignLists` nor `StrictLists` are enabled, its `=>` delimiters are
     * ignored.
     *
     * @prettyphp-callback Assignment operators are aligned unless
     * `MaxAssignmentPadding` is not `null` and would be exceeded.
     *
     * In arrays and `match` expressions, `=>` delimiters are aligned unless
     * `MaxDoubleArrowColumn` is not `null`, in which case any found in
     * subsequent columns are excluded from consideration.
     *
     * Alignment is achieved by:
     *
     * - calculating the difference between the current and desired output
     *   columns of each token
     * - applying it to the `Padding` of the token
     */
    public function processBlock(array $lines): void
    {
        if (count($lines) < 2) {
            return;
        }

        /** @var array<int,array<int,array<int,Token>>> */
        $idx = [];

        $addToIndex = static function (
            int $type,
            int $line,
            Token $token
        ) use (&$idx) {
            $scope = $token->Parent ? $token->Parent->index : -1;
            $idx[$scope][$type][$line] = $token;
        };

        foreach ($lines as $line => $tokens) {
            foreach ($tokens as $token) {
                if ($this->Idx->OperatorAssignment[$token->id]) {
                    /** @var Token */
                    $prev = $token->Prev;
                    /** @var Token */
                    $statement = $token->Statement;
                    if (
                        (
                            !$token->Parent
                            || $token->Parent->Flags & TokenFlag::STRUCTURAL_BRACE
                            || $token->Parent->isParameterList()
                        )
                        // Ignore assignment operators after the first:
                        // - in the statement
                        // - on the line
                        && !$statement->withNextSiblings($prev)
                                      ->hasOneFrom($this->Idx->OperatorAssignment)
                        && !$token->firstSiblingAfterNewline(false)
                                  ->withNextSiblings($prev)
                                  ->hasOneFrom($this->Idx->OperatorAssignment)
                    ) {
                        $addToIndex(self::TYPE_ASSIGNMENT, $line, $token);
                    }
                } elseif ($token->id === \T_DOUBLE_ARROW) {
                    if (
                        $this->Formatter->NewlineBeforeFnDoubleArrow
                        && $token->hasNewlineBefore()
                    ) {
                        continue;
                    }
                    /** @var Token */
                    $prev = $token->Prev;
                    /** @var Token */
                    $statement = $token->Statement;
                    if (
                        $token->Parent
                        && (
                            (
                                $token->Parent->isArrayOpenBracket() && (
                                    $this->ListRuleEnabled
                                    || $token->Parent->hasNewlineBeforeNextCode()
                                )
                            )
                            || $token->Parent->isMatchOpenBrace()
                        )
                        // Ignore `=>`:
                        // - in `yield <key> => ...`
                        // - in `fn(...) => ...`
                        // - after the first `=>` on the line
                        && !$statement->withNextSiblings($prev)->hasOneOf(\T_YIELD)
                        && !$token->isDoubleArrowAfterFn()
                        && !$token->firstSiblingAfterNewline(false)
                                  ->withNextSiblings($prev)
                                  ->hasOneOf(\T_DOUBLE_ARROW)
                    ) {
                        $addToIndex(self::TYPE_DOUBLE_ARROW, $line, $token);
                    }
                }
            }
        }

        /** @var list<Token> */
        $group = [];
        $register = function (int $type) use (&$group) {
            if (count($group) > 1) {
                $maxColumn = $this->MaxColumnByType[$type];
                $maxPadding = $this->MaxPaddingByType[$type];
                $first = reset($group);
                $this->Formatter->registerCallback(
                    static::class,
                    $first,
                    static function () use ($maxColumn, $maxPadding, $group) {
                        $alignable = 0;
                        [$max, $min] = [0, 0];
                        foreach ($group as $i => $token) {
                            $column = $token->getOutputColumn(false);
                            if (
                                $maxColumn !== null
                                && $column - strlen($token->text) > $maxColumn
                            ) {
                                $columns[$i] = -1;
                                continue;
                            }
                            $columns[$i] = $column;
                            [$max, $min] = $alignable++
                                ? [max($max, $column), min($min, $column)]
                                : [$column, $column];
                        }
                        if ($maxPadding !== null && $max - $min > $maxPadding) {
                            return;
                        }
                        foreach ($group as $i => $token) {
                            if ($columns[$i] !== -1) {
                                $token->Padding += $max - $columns[$i];
                            }
                        }
                    }
                );
            }
            $group = [];
        };

        foreach ($idx as $scope) {
            foreach ($scope as $type => $tokens) {
                if (count($tokens) < 2) {
                    continue;
                }

                /** @var Token|null */
                $prev = null;
                $prevLine = null;
                foreach ($tokens as $line => $token) {
                    if ($prev) {
                        /** @var Token */
                        $prevEnd = $prev->EndStatement;
                        /** @var Token */
                        $before = $token->Statement;
                        $before = $before->PrevCode;
                        if (
                            $line - $prevLine > 1
                            && $before
                            && $before->index > $prevEnd->index
                        ) {
                            $register($type);
                        }
                    }
                    $group[] = $token;
                    $prev = $token;
                    $prevLine = $line;
                }
                $register($type);
            }
        }
    }
}
