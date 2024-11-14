<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * If the first token on a new line continues a statement from the previous one,
 * add a hanging indent
 *
 * @api
 */
final class HangingIndentation implements TokenRule
{
    use TokenRuleTrait;

    /**
     * Do not add a hanging indent to children of this parent
     */
    private const NO_INDENT = 1;

    /**
     * Add a hanging indent to mid-statement children of this parent
     */
    private const NORMAL_INDENT = 2;

    /**
     * Add a hanging indent to children of this parent, and an additional indent
     * to mid-statement children
     */
    private const OVERHANGING_INDENT = 4;

    /**
     * There is no newline between this parent and its first child
     */
    private const NO_INNER_NEWLINE = 8;

    private const PARENT_TYPE = TokenData::HANGING_INDENT_PARENT_TYPE;

    private bool $HeredocIndentIsMixed;
    private bool $HeredocIndentIsHanging;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
            case self::CALLBACK:
                return 800;

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $indent = $this->Formatter->HeredocIndent;
        $this->HeredocIndentIsMixed = (bool) ($indent & HeredocIndent::MIXED);
        $this->HeredocIndentIsHanging = (bool) ($indent & HeredocIndent::HANGING);
    }

    /**
     * @inheritDoc
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($this->Idx->OpenBracket[$token->id]) {
                $hasList = (
                    $token->Flags & TokenFlag::LIST_PARENT
                    && $token->Data[TokenData::LIST_ITEM_COUNT] > 1
                ) || (
                    $token->id === \T_OPEN_BRACE && (
                        $token->Flags & TokenFlag::STRUCTURAL_BRACE
                        || $token->isMatchBrace()
                    )
                );
                $token->Data[self::PARENT_TYPE] = $token->hasNewlineBeforeNextCode()
                    ? ($hasList
                        ? self::NORMAL_INDENT
                        : self::NO_INDENT)
                    : ($hasList || ($token->id !== \T_OPEN_BRACE && $token->adjacent())
                        ? self::OVERHANGING_INDENT | self::NO_INNER_NEWLINE
                        : self::NORMAL_INDENT | self::NO_INNER_NEWLINE);
            }

            // Ignore non-code tokens and T_CLOSE_TAG statement terminators
            $prev = $token->PrevCode;
            if ($this->Idx->NotCode[$token->id] || !$prev) {
                continue;
            }

            // If this is the first child of a NO_INDENT parent, create a
            // hanging indent context for it to prevent siblings on subsequent
            // lines being indented
            $parent = $token->Parent;
            if (
                $parent === $prev
                && $token->NextCode
                && $parent->ClosedBy
                && $token->NextCode->Index < $parent->ClosedBy->Index
                && $this->Idx->OpenBracket[$parent->id]
                && $parent->Data[self::PARENT_TYPE] & self::NO_INDENT
            ) {
                /** @var Token */
                $current = $token->Next;
                $context = [$parent, $token];
                do {
                    $current->HangingIndentToken = $token;
                    $current->HangingIndentContext[] = $context;
                    $current->HangingIndentParent[] = $parent;
                } while (($current = $current->Next) && $current !== $parent->ClosedBy);
                continue;
            }

            // Ignore tokens aligned by other rules
            if (
                $token->AlignedWith
                || $this->Idx->CloseBracketOrAlt[$token->id]
                || $this->Idx->HasStatement[$token->id]
            ) {
                continue;
            }

            // Ignore tokens after attributes
            if ($token->PrevSibling && (
                $token->PrevSibling->id === \T_ATTRIBUTE
                || $token->PrevSibling->id === \T_ATTRIBUTE_COMMENT
            )) {
                continue;
            }

            // Ignore tokens that aren't at the beginning of a line and aren't
            // the first token in a heredoc with hanging indentation
            if (!$prev->hasNewlineBeforeNextCode() && !(
                $prev->id === \T_START_HEREDOC
                && ($this->HeredocIndentIsHanging || (
                    $this->HeredocIndentIsMixed
                    && !$prev->AlignedWith
                    && $prev->PrevCode
                    && !$prev->PrevCode->hasNewlineBeforeNextCode()
                ))
            )) {
                continue;
            }

            // Suppress hanging indentation between conditional expressions in
            // `match` and aligned expressions in `for`
            if ($prev->id === \T_COMMA && $parent && (
                (
                    $parent->id === \T_OPEN_BRACE
                    && $prev->isMatchDelimiter()
                ) || (
                    $parent->PrevCode
                    && $parent->PrevCode->id === \T_FOR
                    && ($nextCode = $token->prevSiblingOf(\T_SEMICOLON)->or($parent)->NextCode)
                    && $nextCode->AlignedWith
                )
            )) {
                continue;
            }

            // Ignore open braces, statements that inherit indentation from
            // enclosing brackets, and lines with indentation that differs from
            // the previous line
            if (
                $token->id === \T_OPEN_BRACE
                || ($token->Statement === $token && (
                    !$parent
                    || !$this->Idx->OpenBracket[$parent->id]
                    || !($parent->Data[self::PARENT_TYPE] & self::OVERHANGING_INDENT)
                ))
                || $this->indent($prev) !== $this->indent($token)
            ) {
                continue;
            }

            $trigger = $token->Parent !== $prev->Parent
                || TokenUtil::isNewlineAllowedBefore($token)
                || !TokenUtil::isNewlineAllowedAfter($prev)
                    ? $token
                    : $prev;

            $context = [$token->Parent];
            $latest = $token->HangingIndentToken;
            $apply = $token;
            $until = null;

            // Add an appropriate token to `$context` to establish a context for
            // this level of hanging indentation, and do nothing if it's already
            // been applied on behalf of a previous token
            if (
                $trigger->Flags & TokenFlag::TERNARY_OPERATOR
                || $trigger->id === \T_COALESCE
                || $trigger->id === \T_COALESCE_EQUAL
            ) {
                $context[] = self::getTernaryContext($trigger)
                    ?? self::getTernaryOperator1($trigger)
                    ?? $trigger;
                $until = self::getTernaryEndOfExpression($trigger);
                $apply = $trigger;
            } elseif ($this->Idx->Chain[$token->id]) {
                $context[] = $token->Data[TokenData::CHAIN_OPENED_BY];
            } elseif ($token->Heredoc && $token->Heredoc === $prev) {
                $context[] = $token->Heredoc;
            } elseif (isset($token->Data[TokenData::LIST_PARENT])) {
                $context[] = $token->Data[TokenData::LIST_PARENT];
            } elseif ($latest && $latest->Parent === $token->Parent) {
                if ($this->Idx->ExpressionDelimiter[$trigger->id]) {
                    $context[] = $trigger;
                    $apply = $trigger;
                } elseif ($latest->id === \T_DOUBLE_ARROW) {
                    $context[] = $latest;
                } elseif (
                    $token->Statement !== $token
                    && $latest->Statement === $latest
                ) {
                    if ($token->Expression === $latest->Expression) {
                        $context[] = $latest;
                    } else {
                        $delimiter = $trigger->prevSiblingFrom($this->Idx->ExpressionDelimiterExceptComparison);
                        if ($delimiter->id === \T_NULL) {
                            $context[] = $latest;
                        } else {
                            $startOfLine = $prev->startOfLine();
                            if ($delimiter->Index < $startOfLine->Index) {
                                $context[] = $latest;
                            }
                        }
                    }
                } elseif (
                    $token->Statement !== $token
                    && $latest->Statement !== $latest
                ) {
                    $latest = $latest->HangingIndentToken;
                    if (
                        $latest
                        && $latest->Parent === $token->Parent
                        && $latest->Statement === $latest
                    ) {
                        $context[] = $latest;
                    }
                }
            }

            if (in_array($context, $token->HangingIndentContext, true)) {
                continue;
            }

            // Add indentation for any parents of `$parent` with unapplied
            // hanging indents to ensure indentation accurately represents
            // depth, e.g. in line 2 here:
            //
            // ```php
            // if (!(($baz = $foo->bar(...$args)) &&
            //             $baz->qux()) ||
            //         $baz->quux())
            // ```
            $until = $until ?? $apply->pragmaticEndOfExpression(true, true, true);
            $indent = 0;
            $hanging = [];
            $parents =
                !$parent || in_array($parent, $token->HangingIndentParent, true)
                    ? []
                    : [$parent];
            $current = $parent;
            while ($current
                    && ($current = $current->Parent)
                    && $this->Idx->OpenBracket[$current->id]
                    && $current->Data[self::PARENT_TYPE] & self::NO_INNER_NEWLINE) {
                if (in_array($current, $token->HangingIndentParent, true)) {
                    continue;
                }
                $parents[] = $current;
                $indent++;
                $hanging[$current->Index] = 1;
                if ($current->Data[self::PARENT_TYPE] & self::OVERHANGING_INDENT) {
                    $indent++;
                    $hanging[$current->Index]++;
                }
            }

            // Add at least one level of hanging indentation
            $indent++;

            // And another if the token is mid-statement and has an
            // OVERHANGING_INDENT parent
            if ($parent
                    && $this->Idx->OpenBracket[$parent->id]
                    && $parent->Data[self::PARENT_TYPE] & self::OVERHANGING_INDENT
                    && $token->Statement !== $token) {
                $indent++;
                $hanging[$parent->Index] = 1;
            }

            if ($adjacent = $until->adjacentBeforeNewline()) {
                $until = $adjacent->pragmaticEndOfExpression();
            }

            if ($indent > 1) {
                $this->Formatter->registerCallback(
                    static::class,
                    $token,
                    fn() => $this->maybeCollapseOverhanging($token, $until, $hanging),
                    true
                );
            }

            $current = $token;
            do {
                // Allow multiple levels of hanging indentation within one
                // parent:
                //
                // ```php
                // $a = $b->c(fn() =>
                //         $d &&
                //         $e,
                //     $f &&
                //         $g)
                //     ?: $start;
                // ```
                if ($parent
                        && ($hanging[$parent->Index] ?? null)
                        && array_key_exists($parent->Index, $current->HangingIndentParentLevels)) {
                    $current->HangingIndentParentLevels[$parent->Index] += $hanging[$parent->Index];
                }
                $current->HangingIndent += $indent;
                $current->HangingIndentParentLevels += $hanging;
                if ($current !== $token) {
                    $current->HangingIndentToken = $apply;
                    $current->HangingIndentContext[] = $context;
                    array_push($current->HangingIndentParent, ...$parents);
                }
            } while (
                $current !== $until
                && $current = $current->Next
            );
        }
    }

    /**
     * Get the first ternary or null coalescing operator that is one of another
     * ternary or null coalescing operator's preceding siblings in the same
     * statement
     *
     * Prevents outcomes like this:
     *
     * ```php
     * $a
     *   ?: $b
     *     ?: $c
     * ```
     */
    public static function getTernaryContext(Token $token): ?Token
    {
        $current = $token->PrevSibling;
        $prevTernary = null;
        while ($current && $current->Statement === $token->Statement) {
            if (
                $current->id === \T_COALESCE
                || $current->id === \T_COALESCE_EQUAL
                || ($current->Flags & TokenFlag::TERNARY_OPERATOR
                    && self::getTernaryOperator1($current) === $current
                    && $current->Data[TokenData::OTHER_TERNARY_OPERATOR]->Index
                        < (self::getTernaryOperator1($token) ?? $token)->Index)
            ) {
                $prevTernary = $current;
            }
            $current = $current->PrevSibling;
        }

        // Handle this scenario:
        //
        // ```php
        // $foo = $bar
        //     ? $qux[$i] ?? $fallback
        //     : $quux;
        // ```
        if ($prevTernary
                && $token->id === \T_COLON
                && $token->Flags & TokenFlag::TERNARY_OPERATOR
                && $prevTernary->Index > $token->Data[TokenData::OTHER_TERNARY_OPERATOR]->Index) {
            return null;
        }

        return $prevTernary;
    }

    /**
     * Get the last token in the same statement as a ternary operator
     */
    public static function getTernaryEndOfExpression(Token $token): Token
    {
        // Find the last token
        // - in the third expression
        // - of the last ternary expression in this statement
        $current = $token;
        do {
            if (
                $current->id === \T_COALESCE
                || $current->id === \T_COALESCE_EQUAL
            ) {
                $until = $current->EndExpression ?? $current;
            } else {
                /** @var Token */
                $until = self::getTernaryOperator2($current);
                $until = $until->EndExpression ?? $current;
            }
        } while ($until !== $current
            && ($current = $until->NextSibling)
            && ($current->id === \T_COALESCE
                || $current->id === \T_COALESCE_EQUAL
                || ($current->id === \T_QUESTION
                    && $current->Flags & TokenFlag::TERNARY_OPERATOR)));

        // And without breaking out of an unenclosed control structure
        // body, proceed to the end of the expression
        if (!(
            $until->NextSibling
            && $until->NextSibling->Flags & TokenFlag::TERNARY_OPERATOR
        )) {
            $until = $until->pragmaticEndOfExpression();
        }

        return $until;
    }

    private static function getTernaryOperator1(Token $token): ?Token
    {
        return $token->id === \T_QUESTION
            ? $token
            : ($token->Flags & TokenFlag::TERNARY_OPERATOR
                ? $token->Data[TokenData::OTHER_TERNARY_OPERATOR]
                : null);
    }

    private static function getTernaryOperator2(Token $token): ?Token
    {
        return $token->id === \T_COLON
            ? $token
            : ($token->Flags & TokenFlag::TERNARY_OPERATOR
                ? $token->Data[TokenData::OTHER_TERNARY_OPERATOR]
                : null);
    }

    /**
     * @param array<int,int> $hanging
     */
    private function maybeCollapseOverhanging(Token $token, Token $until, array $hanging): void
    {
        $tokens = $token->collect($until);
        foreach ($hanging as $index => $count) {
            for ($i = 0; $i < $count; $i++) {
                // The purpose of overhanging indents is to visually separate
                // distinct blocks of code that would otherwise run together, so
                // for every level of collapsible indentation, ensure subsequent
                // lines with different indentation levels will remain distinct
                // if a level is removed
                $current = $token;
                do {
                    $indent = $this->effectiveIndent($current);

                    // Find the next line with an indentation level that differs
                    // from the current line
                    $next = $current;
                    $nextIndent = 0;
                    do {
                        do {
                            $next = $next->endOfLine(false);
                            if (!$next->isMultiLineComment() || !$next->hasNewline()) {
                                break;
                            }
                            // If a comment that breaks over multiple lines
                            // appears on the same line as adjacent code, stop
                            // checking for collapsible indentation levels
                            if (!$next->hasNewlineBefore()) {
                                $next = null;
                                break 2;
                            }
                            if ($next->hasNewlineAfter()) {
                                break;
                            }
                            $next = $next->Next;
                            if (!$next) {
                                break 2;
                            }
                        } while (true);
                        $next = $next->Next;
                        if (!$next) {
                            break;
                        }
                        $nextIndent = $this->effectiveIndent($next);
                    } while ($nextIndent === $indent
                        && $next->Index <= $until->Index);

                    // Adjust $indent for this level of indentation
                    $indent--;

                    // Make the same adjustment to $nextIndent if:
                    // - $next falls in the range being collapsed, and
                    // - $next still has at least one level of uncollapsed
                    //   indentation associated with this parent
                    //
                    // or, to trigger alignment of expressions that shouldn't
                    // appear to be distinct, if:
                    // - $next falls outside the range being collapsed
                    // - $next has the same hanging indent context as $token
                    if ($next
                        && (($next->Index <= $until->Index
                                && ($next->HangingIndentParentLevels[$index] ?? 0))
                            || ($next->Index > $until->Index
                                && $next->Parent === $token->Parent
                                && $next->HangingIndentContext === $token->HangingIndentContext
                                && !(($next->Statement === $next) xor ($token->Statement === $token))))) {
                        $nextIndent--;
                    }

                    if ($next && $nextIndent === $indent) {
                        return;
                    }

                    $current = $next;
                } while ($current && $current->Index <= $until->Index);

                foreach ($tokens as $t) {
                    if ($t->HangingIndentParentLevels[$index] ?? 0) {
                        $t->HangingIndent--;
                        $t->HangingIndentParentLevels[$index]--;
                    }
                }
            }
        }
    }

    private function indent(Token $token): int
    {
        return $token->PreIndent + $token->Indent - $token->Deindent;
    }

    private function effectiveIndent(Token $token): int
    {
        // Ignore $token->LineUnpadding given its role in alignment
        return (int) (($token->indent() * $this->Formatter->TabSize
            + $token->LinePadding
            + $token->Padding) / $this->Formatter->TabSize);
    }
}
