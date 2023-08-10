<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\HeredocIndent;
use Lkrms\Pretty\Php\Concern\MultiTokenRuleTrait;
use Lkrms\Pretty\Php\Contract\MultiTokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * If the first token on a new line continues a statement from the previous one,
 * add a hanging indent
 *
 * @api
 */
final class HangingIndentation implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    /**
     * Do not add a hanging indent to children of this parent
     */
    private const NO_INDENT = 2 ** 0;

    /**
     * Add a hanging indent to mid-statement children of this parent
     */
    private const NORMAL_INDENT = 2 ** 1;

    /**
     * Add a hanging indent to children of this parent, and an additional indent
     * to mid-statement children
     */
    private const OVERHANGING_INDENT = 2 ** 2;

    /**
     * There is no newline between this parent and its first child
     */
    private const NO_INNER_NEWLINE = 2 ** 3;

    /**
     * True if `$this->Formatter->HeredocIndent` is MIXED or HANGING
     */
    private bool $HeredocMayHaveHangingIndent;

    /**
     * True if `$this->Formatter->HeredocIndent` is HANGING
     */
    private bool $HeredocHasHangingIndent;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 800;

            default:
                return null;
        }
    }

    public function reset(): void
    {
        $indent = $this->Formatter->HeredocIndent;
        $this->HeredocMayHaveHangingIndent =
            (bool) ($indent & (HeredocIndent::MIXED | HeredocIndent::HANGING));
        $this->HeredocHasHangingIndent =
            (bool) ($indent & HeredocIndent::HANGING);
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($this->TypeIndex->OpenBracket[$token->id]) {
                $token->HangingIndentParentType =
                    $token->hasNewlineAfterCode()
                        ? ($token->IsListParent ||
                            ($token->id === T_OPEN_BRACE && $token->isStructuralBrace())
                                ? self::NORMAL_INDENT
                                : self::NO_INDENT)
                        : ($token->IsListParent ||
                            ($token->id === T_OPEN_BRACE && $token->isStructuralBrace()) ||
                            ($token->id !== T_OPEN_BRACE && $token->adjacent())
                                ? self::OVERHANGING_INDENT | self::NO_INNER_NEWLINE
                                : self::NORMAL_INDENT | self::NO_INNER_NEWLINE);
            }

            // If this is the first child of a NO_INDENT parent, create a
            // hanging indent context for it to prevent siblings on subsequent
            // lines being indented
            $parent = $token->Parent;
            if ($token->IsCode &&
                    $parent &&
                    $parent === $token->_prevCode &&
                    $token !== $parent->ClosedBy &&
                    ($parent->HangingIndentParentType & self::NO_INDENT)) {
                $current = $token->_next;
                $stack = [$token->BracketStack];
                $stack[] = $token;
                do {
                    $current->HangingIndentStack[] = $token;
                    $current->HangingIndentContextStack[] = $stack;
                    $current->HangingIndentParentStack[] = $parent;
                } while (($current = $current->_next) &&
                    $current !== $parent->ClosedBy);
                continue;
            }

            // Ignore tokens aligned by other rules
            $prevCode = $token->_prevCode;
            if ($token->AlignedWith ||
                    $this->TypeIndex->NotCode[$token->id] ||
                    $this->TypeIndex->CloseBracketOrEndAltSyntax[$token->id] ||
                    $this->TypeIndex->HasStatement[$token->id] ||
                    !$prevCode) {
                continue;
            }

            // Ignore tokens after attributes
            if ($token->_prevSibling &&
                ($token->_prevSibling->id === T_ATTRIBUTE ||
                    $token->_prevSibling->id === T_ATTRIBUTE_COMMENT)) {
                continue;
            }

            // Ignore tokens that aren't at the beginning of a line (including
            // heredocs if applicable)
            if (!$prevCode->hasNewlineAfterCode() &&
                !($this->HeredocMayHaveHangingIndent &&
                    $prevCode->id === T_START_HEREDOC &&
                    ($this->HeredocHasHangingIndent ||
                        ($prevCode->_prevCode &&
                            !$prevCode->AlignedWith &&
                            !$prevCode->_prevCode->hasNewlineAfterCode())))) {
                continue;
            }

            // Suppress hanging indentation between conditional expressions in
            // `match`
            if ($prevCode->id === T_COMMA &&
                    $parent &&
                    $parent->id === T_OPEN_BRACE &&
                    $prevCode->isMatchDelimiter()) {
                continue;
            }

            // Ignore open braces, statements that inherit indentation from
            // enclosing brackets, and lines with different indentation levels
            // from the previous line
            if ($token->id === T_OPEN_BRACE ||
                    ($token->Statement === $token &&
                        (!$parent ||
                            !($parent->HangingIndentParentType & self::OVERHANGING_INDENT))) ||
                    $this->indent($prevCode) !== $this->indent($token)) {
                continue;
            }

            $stack = [$token->BracketStack];
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
            // Or between ternary operators and their predecessors:
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
            if ($token->IsTernaryOperator) {
                $stack[] = self::getTernaryContext($token) ?: $token->TernaryOperator1;
                $until = self::getTernaryEndOfExpression($token);
            } elseif ($token->ChainOpenedBy) {
                $stack[] = $token->ChainOpenedBy;
            } elseif ($token->ListParent) {
                $stack[] = $token->ListParent;
            } elseif ($latest && $latest->BracketStack === $token->BracketStack) {
                if ($token->Expression === $token) {
                    $stack[] = $token;
                } elseif ($latest->Expression === $latest) {
                    $stack[] = $latest;
                } elseif ($latest->id === T_DOUBLE_ARROW) {
                    $stack[] = $latest;
                } elseif ($token->Statement !== $token &&
                        $latest->Statement === $latest) {
                    $stack[] = $latest;
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
            // if (!(($comment = $line->getLastOf(...TokenType::COMMENT)) &&
            //             $comment->hasNewlineAfter()) ||
            //         $comment->hasNewline())
            // ```
            $until = $until ?? $token->pragmaticEndOfExpression();
            $indent = 0;
            $hanging = [];
            $parents =
                !$parent || in_array($parent, $token->HangingIndentParentStack, true)
                    ? []
                    : [$parent];
            $current = $parent;
            while ($current &&
                    ($current = $current->Parent) &&
                    ($current->HangingIndentParentType & self::NO_INNER_NEWLINE)) {
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

            // And another if the token is mid-statement and `$parent` has
            // overhanging indents
            if ($parent &&
                    ($parent->HangingIndentParentType & self::OVERHANGING_INDENT) &&
                    $token->Statement !== $token) {
                $indent++;
                $hanging[$parent->Index] = 1;
            }

            if ($adjacent = $until->adjacentBeforeNewline()) {
                $until = $adjacent->pragmaticEndOfExpression();
            }

            if ($indent > 1) {
                $this->Formatter->registerCallback(
                    $this,
                    $token,
                    fn() => $this->maybeCollapseOverhanging($token, $until, $hanging),
                    800,
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
                //     $d &&
                //         $e)
                //     ?: $start;
                // ```
                if ($parent &&
                        ($hanging[$parent->Index] ?? null) &&
                        array_key_exists($parent->Index, $current->HangingIndentParentLevels)) {
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
                $current !== $until &&
                $current = $current->_next
            );
        }
    }

    /**
     * Get the first ternary operator that is one of another ternary operator's
     * preceding siblings in the same statement
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
        $current = $token->_prevSibling;
        $prevTernary = null;
        while ($current &&
                $current->Statement === $token->Statement) {
            if ($current->IsTernaryOperator &&
                    $current->TernaryOperator1 === $current &&
                    $current->TernaryOperator2->Index < $token->TernaryOperator1->Index) {
                $prevTernary = $current;
            }
            $current = $current->_prevSibling;
        }

        return $prevTernary;
    }

    /**
     * Get the last token in the same statement as a ternary operator
     *
     */
    public static function getTernaryEndOfExpression(Token $token): Token
    {
        // Find the last token
        // - in the third expression
        // - of the last ternary expression in this statement
        $current = $token;
        do {
            $until = $current->TernaryOperator2->EndExpression ?: $current;
        } while ($until !== $current &&
            ($current = $until->_nextSibling) &&
            $current->IsTernaryOperator &&
            $current->TernaryOperator1 === $current);

        // And without breaking out of an unenclosed control structure
        // body, proceed to the end of the expression
        if (!($until->_nextSibling &&
                $until->_nextSibling->IsTernaryOperator)) {
            $until = $until->pragmaticEndOfExpression();
        }

        return $until;
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
                        $next = $next->endOfLine()->_next;
                        if (!$next) {
                            break;
                        }
                        $nextIndent = $this->effectiveIndent($next);
                    } while ($nextIndent === $indent &&
                        $next->Index <= $until->Index);
                    // Drop $indent and $nextIndent (if $next falls between
                    // $token and $until and this hanging indent hasn't already
                    // been collapsed) for comparison
                    $unit = 1;
                    $indent -= $unit;
                    $nextIndent =
                        !$next ||
                            $next->Index > $until->Index ||
                            !($next->HangingIndentParentLevels[$index] ?? 0)
                                ? $nextIndent
                                : $nextIndent - $unit;
                    if ($nextIndent === $indent && $next) {
                        return;
                    }
                } while (($current = $next) &&
                    $current->Index <= $until->Index);

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