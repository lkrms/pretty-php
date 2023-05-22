<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Align arguments and array elements with their enclosing brackets
 *
 * For example:
 *
 * ```php
 * some_function($with, $several,
 *               $arguments);
 * ```
 */
final class AlignLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 400;
    }

    private const BEFORE_ALIGNABLE_LIST = [
        T_EXTENDS,
        T_FN,
        T_FUNCTION,
        T_IMPLEMENTS,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_QUALIFIED,
        T_NAME_RELATIVE,
        T_NS_SEPARATOR,
        T_STRING,
        T_VARIABLE,
    ];

    public function processList(Token $owner, TokenCollection $items): void
    {
        // Do nothing if:
        // - there's a newline after $owner
        // - there's a newline before $owner's close bracket (if applicable), or
        // - $owner is `(` or `[`, and neither $owner nor any of its `(` or `[`
        //   parents open an array or have a predecessor listed in
        //   self::BEFORE_ALIGNABLE_LIST
        if ($owner->hasNewlineAfterCode() ||
            ($owner->ClosedBy &&
                ((!$this->Formatter->MirrorBrackets &&
                        $owner->ClosedBy->hasNewlineBeforeCode()) ||
                    ($owner->is([T['('], T['[']]) &&
                        !$owner->withParentsWhile(T['('], T['['])
                               ->find(
                                   fn(Token $t): bool =>
                                       ($t->prevCode()->is(self::BEFORE_ALIGNABLE_LIST) &&
                                               ($t === $owner || $t->nextCode()->AlignedWith === $t)) ||
                                           $t->isArrayOpenBracket()
                               ))))) {
            return;
        }

        $multiLineItems =
            $items->filter(
                fn(Token $t, ?Token $next) =>
                    $t->collect($next ? $next->prevCode() : $t->pragmaticEndOfExpression())
                      ->find(fn(Token $t, ?Token $next, ?Token $prev) =>
                                 $prev && $t->IsCode && $t->hasNewlineBefore())
            );

        if ($count = $multiLineItems->count()) {
            // Do nothing if
            // - the token at the end of the first line of every multi-line item
            //   is an open bracket or has a subsequent ternary operator
            // - there are no line breaks between items
            //
            // ```php
            // a($b, [
            //     $c
            // ], $d);
            // ```
            $eolBracketItems = $multiLineItems->filter(
                fn(Token $t, ?Token $next) =>
                    (($eol = $t->endOfLine())->is([T['('], T['['], T['{']]) ||
                            ($eol = $eol->nextCode())->IsTernaryOperator) &&
                        ($next
                            ? $next->prevCode()
                            : $t->pragmaticEndOfExpression())->Index >= $eol->Index
            );
            if ($eolBracketItems->count() === $count &&
                    !$items->find(fn(Token $t) => $t->hasNewlineBefore())) {
                return;
            }

            // Add newlines before multi-line items that don't open with
            // `BRACKET* LINE`, otherwise hanging indents will be ambiguous
            $first = $items->first();
            $multiLineItems
                ->filter(
                    fn(Token $t) =>
                        $t !== $first &&
                            !$t->hasNewlineBefore() &&
                            !$t->withNextCodeWhile(T['('], T['['], T['{'], T_ELLIPSIS, ...self::BEFORE_ALIGNABLE_LIST)
                               ->has($t->endOfLine())
                )
                ->forEach(
                    fn(Token $t) =>
                        $t->WhitespaceBefore |= WhitespaceType::LINE
                );
        } elseif ($items->count() < 2 ||
                !$items->find(fn(Token $t) => $t->hasNewlineBefore())) {
            // Do nothing if there are no multi-line items and no line breaks
            // between items
            return;
        }

        $items->forEach(
            fn(Token $t) =>
                $t->AlignedWith = $owner
        );

        $this->Formatter->registerCallback(
            $this,
            $items->first(),
            fn() => $this->alignList($owner, $items),
            710
        );
    }

    private function alignList(Token $owner, TokenCollection $items): void
    {
        $delta = $owner->alignmentOffset() + ($owner->ClosedBy ? 0 : 1);
        $first = $items->first();
        $items->forEach(
            function (Token $t, ?Token $next) use ($owner, $delta, $first) {
                // `$delta` is added to `LinePadding` for every token between
                // `$t` and `$until`, then between `$until` and either
                // `$untilUpper` or an open bracket at the end of a line,
                // whichever comes first
                $untilUpper = null;
                if ($next) {
                    $until = $next->prevCode()->prev();
                } else {
                    $until = $owner->ClosedBy
                        ? $owner->ClosedBy->prev()
                        : $t->pragmaticEndOfExpression();
                    if ($owner->ClosedBy &&
                            ($adjacent = $until->adjacentBeforeNewline()) &&
                            // @phpstan-ignore-next-line
                            ($adjacent = $adjacent->pragmaticEndOfExpression()) &&
                            // Don't propagate line padding to adjacent code if
                            // it's only been applied to a one-line block
                            $first->collect($until)->hasNewline()) {
                        $untilUpper = $adjacent;
                    }
                }
                $current = $t;
                while (!$current->IsNull) {
                    $current->LinePadding += $delta;
                    if ($current === ($untilUpper ?: $until)) {
                        return;
                    }
                    $current = $current->next();
                    if ($current->isStrictOpenBracket() &&
                            $current->hasNewlineAfterCode() &&
                            $current->Index > $until->Index) {
                        return;
                    }
                }
            }
        );
    }
}
