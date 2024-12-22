<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Align arguments, array elements and other list items with their parents
 *
 * @api
 */
final class AlignLists implements ListRule
{
    use ListRuleTrait;

    /** @var array<int,bool> */
    private array $Parents;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 400,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Parents = [];
    }

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * A callback is registered to align arguments, array elements and other
     * list items, along with their inner and adjacent tokens, with the column
     * after their open brackets, or with the first item in the list if they
     * have no enclosing brackets.
     */
    public function processList(Token $parent, TokenCollection $items): void
    {
        /** @var Token */
        $first = $items->first();
        if ($last = $parent->CloseBracket) {
            /** @var Token */
            $until = $last->PrevCode;
        } else {
            /** @var Token */
            $last = $parent->nextSiblingFrom($this->Idx->OpenBraceOrImplements)
                           ->PrevCode;
            $until = $last;
        }

        // Do nothing if a list of interfaces has a leading newline, or if items
        // don't break over multiple lines
        if (
            (!$parent->CloseBracket && $parent->hasNewlineBeforeNextCode())
            || !$first->collect($last)->hasNewline()
        ) {
            $this->Parents[$parent->index] = false;
            return;
        }

        $this->Parents[$parent->index] = true;

        foreach ($items as $item) {
            $item->AlignedWith = $parent;
        }

        $tabSize = $this->Formatter->TabSize;
        $parents = &$this->Parents;

        $this->Formatter->registerCallback(
            static::class,
            $first,
            static function () use (
                $parent,
                $items,
                $first,
                $last,
                $until,
                $tabSize,
                &$parents
            ) {
                $callback = static function (
                    Token $from,
                    Token $to,
                    int $delta
                ) use ($parent) {
                    // e.g.
                    //
                    // ```
                    // foo([0, 1,
                    //      2], [3,
                    //           4, 5]) + bar([6,
                    //                         7, 8], [9, 10,
                    //                                 11]);
                    if ($parent->CloseBracket) {
                        // `]` after `2` -> `]` after `5`
                        while (($adjacent = $to->lastSiblingBeforeNewline()) !== $to && !(
                            $adjacent->id === \T_OPEN_BRACE
                            && $adjacent->Flags & TokenFlag::STRUCTURAL_BRACE
                            && $adjacent->Depth <= $parent->Depth
                        )) {
                            $to = $adjacent;
                        }
                        // `]` after `5` -> `+` -> `)` after `11`
                        while (($adjacent = $to->adjacentBeforeNewline()) && !(
                            $adjacent->id === \T_OPEN_BRACE
                            && $adjacent->Flags & TokenFlag::STRUCTURAL_BRACE
                            && $adjacent->Depth <= $parent->Depth
                        )) {
                            $to = TokenUtil::getOperatorEndExpression($adjacent);
                        }
                    }
                    foreach ($from->collect($to) as $item) {
                        $item->LinePadding += $delta;
                    }
                };

                // If the first item has no leading newline, apply the callback
                // to the entire list as a single item so inner tokens align
                // with the column after the open bracket
                if (!$parent->hasNewlineBeforeNextCode()) {
                    $delta = $parent->getOutputColumn(false) - 1
                        - ($parent->getIndent() * $tabSize
                            + $parent->LinePadding
                            - $parent->LineUnpadding)
                        + ($parent->CloseBracket ? 0 : 1);
                    if ($delta) {
                        $callback($first, $last, $delta);
                    }
                }

                $items->forEach(
                    static function (
                        Token $item,
                        ?Token $next
                    ) use ($until, $tabSize, $parents, $callback) {
                        // Only apply the callback to nested lists once
                        if (
                            $item->Flags & TokenFlag::LIST_PARENT
                            && $parents[$item->index]
                        ) {
                            return;
                        }

                        $delta = $item->getOutputColumn(true) - 1
                            - ($item->getIndent() * $tabSize
                                + $item->LinePadding
                                - $item->LineUnpadding);
                        if ($delta) {
                            if ($next) {
                                /** @var Token */
                                $delim = $next->PrevCode;
                                /** @var Token */
                                $until = $delim->PrevCode;
                            }
                            $callback($item, $until, $delta);
                        }
                    }
                );
            }
        );
    }
}
