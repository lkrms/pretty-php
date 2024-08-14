<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Token\Token;

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

    /**
     * True if `$this->Formatter->HeredocIndent` is MIXED or HANGING
     */
    private bool $HeredocMayHaveHangingIndent;

    /**
     * True if `$this->Formatter->HeredocIndent` is HANGING
     */
    private bool $HeredocHasHangingIndent;

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
    public function reset(): void
    {
        if (isset($this->HeredocHasHangingIndent)) {
            return;
        }

        /** @var int&HeredocIndent::* */
        $indent = $this->Formatter->HeredocIndent;
        $this->HeredocMayHaveHangingIndent =
            (bool) ($indent & (HeredocIndent::MIXED | HeredocIndent::HANGING));
        $this->HeredocHasHangingIndent =
            (bool) ($indent & HeredocIndent::HANGING);
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($this->Idx->OpenBracket[$token->id]) {
                $token->HangingIndentParentType =
                    $token->hasNewlineBeforeNextCode()
                        ? (($token->Flags & TokenFlag::LIST_PARENT
                                && $token->Data[TokenData::LIST_ITEM_COUNT] > 1)
                            || ($token->id === \T_OPEN_BRACE && $token->isStructuralBrace(true))
                                ? self::NORMAL_INDENT
                                : self::NO_INDENT)
                        : (($token->Flags & TokenFlag::LIST_PARENT
                                && $token->Data[TokenData::LIST_ITEM_COUNT] > 1)
                            || ($token->id === \T_OPEN_BRACE && $token->isStructuralBrace(true))
                            || ($token->id !== \T_OPEN_BRACE && $token->adjacent())
                                ? self::OVERHANGING_INDENT | self::NO_INNER_NEWLINE
                                : self::NORMAL_INDENT | self::NO_INNER_NEWLINE);
            }

            // If this is the first child of a NO_INDENT parent, create a
            // hanging indent context for it to prevent siblings on subsequent
            // lines being indented
            $parent = $token->Parent;
            if ($token->Flags & TokenFlag::CODE
                    && $parent
                    && $parent === $token->PrevCode
                    && $token->NextCode
                    && $parent->ClosedBy
                    && $token->NextCode->Index < $parent->ClosedBy->Index
                    && ($parent->HangingIndentParentType & self::NO_INDENT)) {
                /** @var Token */
                $current = $token->Next;
                $stack = [[$parent]];
                $stack[] = $token;
                do {
                    $current->HangingIndentStack[] = $token;
                    $current->HangingIndentContextStack[] = $stack;
                    $current->HangingIndentParentStack[] = $parent;
                } while (($current = $current->Next)
                    && $current !== $parent->ClosedBy);
                continue;
            }

            // Ignore tokens aligned by other rules
            $prevCode = $token->PrevCode;
            if ($token->AlignedWith
                    || $this->Idx->NotCode[$token->id]
                    || $this->Idx->CloseBracketOrEndAltSyntax[$token->id]
                    || $this->Idx->HasStatement[$token->id]
                    || !$prevCode) {
                continue;
            }

            // Ignore tokens after attributes
            if ($token->PrevSibling
                && ($token->PrevSibling->id === \T_ATTRIBUTE
                    || $token->PrevSibling->id === \T_ATTRIBUTE_COMMENT)) {
                continue;
            }

            // Ignore tokens that aren't at the beginning of a line (including
            // heredocs if applicable)
            if (!$prevCode->hasNewlineBeforeNextCode()
                && !($this->HeredocMayHaveHangingIndent
                    && $prevCode->id === \T_START_HEREDOC
                    && ($this->HeredocHasHangingIndent
                        || ($prevCode->PrevCode
                            && !$prevCode->AlignedWith
                            && !$prevCode->PrevCode->hasNewlineBeforeNextCode())))) {
                continue;
            }

            // Suppress hanging indentation between conditional expressions in
            // `match`
            if ($prevCode->id === \T_COMMA
                    && $parent
                    && $parent->id === \T_OPEN_BRACE
                    && $prevCode->isMatchDelimiter()) {
                continue;
            }

            // Suppress hanging indentation between aligned expressions in `for`
            // loops
            if ($parent
                    && $parent->PrevCode
                    && $parent->PrevCode->id === \T_FOR
                    && $prevCode->id === \T_COMMA
                    && ($nextCode = $token->prevSiblingOf(\T_SEMICOLON)->or($parent)->NextCode)
                    && $nextCode->AlignedWith) {
                continue;
            }

            // Ignore open braces, statements that inherit indentation from
            // enclosing brackets, and lines with different indentation levels
            // from the previous line
            if ($token->id === \T_OPEN_BRACE
                    || ($token->Statement === $token
                        && (!$parent
                            || !($parent->HangingIndentParentType & self::OVERHANGING_INDENT)))
                    || $this->indent($prevCode) !== $this->indent($token)) {
                continue;
            }

            $stack = [[$token->Parent]];
            $latest = end($token->HangingIndentStack);
            unset($until);

            // Add an appropriate token to `$stack` to establish a context for
            // this level of hanging indentation. If `$stack` matches a context
            // already applied on behalf of a previous token, return without
            // doing anything.
            //
            // The aim is to differentiate between, say, lines where a new
            // expression starts, and lines where an expression continues:
            //
            // ```php
            // $iterator = new RecursiveDirectoryIterator($dir,
            //     FilesystemIterator::KEY_AS_PATHNAME |
            //         FilesystemIterator::CURRENT_AS_FILEINFO |
            //         FilesystemIterator::SKIP_DOTS);
            // ```
            //
            // Or between ternary operators and the expressions they belong to:
            //
            // ```php
            // return is_string($contents)
            //     ? $contents
            //     : json_encode($contents, JSON_PRETTY_PRINT);
            // ```
            //
            // Or between the start of a ternary expression and a continued one:
            //
            // ```php
            // fn($a, $b) =>
            //     $a === $b
            //         ? 0
            //         : $a <=>
            //             $b;
            // ```
            if (
                ($token->Flags & TokenFlag::TERNARY_OPERATOR)
                || $token->id === \T_COALESCE
                || $token->id === \T_COALESCE_EQUAL
            ) {
                $stack[] = self::getTernaryContext($token)
                    ?? self::getTernaryOperator1($token)
                    ?? $token;
                $until = self::getTernaryEndOfExpression($token);
            } elseif ($this->Idx->Chain[$token->id]) {
                $stack[] = $token->Data[TokenData::CHAIN_OPENED_BY];
            } elseif ($token->Heredoc && $token->Heredoc === $prevCode) {
                $stack[] = $token->Heredoc;
            } elseif (isset($token->Data[TokenData::LIST_PARENT])) {
                $stack[] = $token->Data[TokenData::LIST_PARENT];
            } elseif ($latest && $latest->Parent === $token->Parent) {
                if ($this->Idx->ExpressionDelimiter[$prevCode->id]
                        || $this->Idx->ExpressionDelimiter[$token->id]) {
                    $stack[] = $token;
                } elseif ($latest->id === \T_DOUBLE_ARROW) {
                    $stack[] = $latest;
                } elseif ($token->Statement !== $token
                        && $latest->Statement === $latest) {
                    if ($token->Expression === $latest->Expression) {
                        $stack[] = $latest;
                    } else {
                        $delimiter = $token->prevSiblingOf(
                            \T_DOUBLE_ARROW,
                            ...TokenType::OPERATOR_ASSIGNMENT,
                        );
                        if ($delimiter->id === \T_NULL) {
                            $stack[] = $latest;
                        } else {
                            $startOfLine = $prevCode->startOfLine();
                            if ($delimiter->Index < $startOfLine->Index) {
                                $stack[] = $latest;
                            }
                        }
                    }
                } elseif ($token->Statement !== $token
                        && $latest->Statement !== $latest) {
                    $latest = end($latest->HangingIndentStack);
                    if ($latest
                            && $latest->Parent === $token->Parent
                            && $latest->Statement === $latest) {
                        $stack[] = $latest;
                    }
                }
            }

            // If a hanging indent has already been applied to a token with the
            // same stack, don't add it again
            if (in_array($stack, $token->HangingIndentContextStack, true)) {
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
            $until = $until ?? $token->pragmaticEndOfExpression();
            $indent = 0;
            $hanging = [];
            $parents =
                !$parent || in_array($parent, $token->HangingIndentParentStack, true)
                    ? []
                    : [$parent];
            $current = $parent;
            while ($current
                    && ($current = $current->Parent)
                    && ($current->HangingIndentParentType & self::NO_INNER_NEWLINE)) {
                if (in_array($current, $token->HangingIndentParentStack, true)) {
                    continue;
                }
                $parents[] = $current;
                $indent++;
                $hanging[$current->Index] = 1;
                if ($current->HangingIndentParentType & self::OVERHANGING_INDENT) {
                    $indent++;
                    $hanging[$current->Index]++;
                }
            }

            // Add at least one level of hanging indentation
            $indent++;

            // And another if the token is mid-statement and has an
            // OVERHANGING_INDENT parent
            if ($parent
                    && ($parent->HangingIndentParentType & self::OVERHANGING_INDENT)
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
                    $current->HangingIndentStack[] = $token;
                    $current->HangingIndentContextStack[] = $stack;
                    array_push($current->HangingIndentParentStack, ...$parents);
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
                    && ($current->Flags & TokenFlag::TERNARY_OPERATOR))));

        // And without breaking out of an unenclosed control structure
        // body, proceed to the end of the expression
        if (!(
            $until->NextSibling
            && ($until->NextSibling->Flags & TokenFlag::TERNARY_OPERATOR)
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
                                && $next->HangingIndentContextStack === $token->HangingIndentContextStack
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
        return
            (int) (($token->indent() * $this->Formatter->TabSize
                + $token->LinePadding
                + $token->Padding) / $this->Formatter->TabSize);
    }
}
