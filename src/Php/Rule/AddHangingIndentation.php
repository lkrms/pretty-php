<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * If the first token on a new line continues a statement from the previous one,
 * add a hanging indent
 *
 */
class AddHangingIndentation implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 800;
    }

    public function processToken(Token $token): void
    {
        if ($token->is([T['('], T['['], T['{']]) && !$token->hasNewlineAfterCode()) {
            $token->IsHangingParent = true;
            $token->IsOverhangingParent =
                // Does it have delimited values? (e.g. `list(var, var)`)
                $token->innerSiblings()->hasOneOf(T[',']) ||
                    // Delimited expressions? (e.g. `for (expr; expr; expr)`)
                    ($token->is(T['(']) && $token->innerSiblings()->hasOneOf(T[';'])) ||
                    // A subsequent statement or block? (e.g. `if (expr)
                    // statement`)
                    $token->adjacent();
        }

        if (!$this->isHanging($token)) {
            return;
        }

        $stack  = [$token->BracketStack];
        $latest = end($token->IndentStack);
        $prev   = $token->prevCode();
        $parent = $token->parent();

        // Add an appropriate token to `$stack` to establish a context for this
        // level of hanging indentation. If `$stack` matches a context already
        // applied on behalf of a previous token, return without doing anything.
        //
        // The aim is to differentiate between, say, lines where a new
        // expression starts, and lines where an expression continues:
        //
        //     $iterator = new RecursiveDirectoryIterator($dir,
        //         FilesystemIterator::KEY_AS_PATHNAME |
        //             FilesystemIterator::CURRENT_AS_FILEINFO |
        //             FilesystemIterator::SKIP_DOTS);
        //
        // Or between ternary operators and their predecessors:
        //
        //     return is_string($contents)
        //         ? $contents
        //         : json_encode($contents, JSON_PRETTY_PRINT);
        //
        // Or between the start of a ternary expression and a continued one:
        //
        //     fn($a, $b) =>
        //         $a === $b
        //             ? 0
        //             : $a <=>
        //                 $b;
        //
        if ($token->IsTernaryOperator) {
            // Avoid outcomes like this by adding the earliest possible ternary
            // operator to the stack:
            //
            //     $a
            //       ?: $b
            //         ?: $c
            //           ?: $d
            //
            $prevTernary =
                $token->prevSiblings()
                      ->filter(
                          fn(Token $t) =>
                              $t->IsTernaryOperator &&
                                  $t->TernaryOperator2->Index < $token->TernaryOperator1->Index
                      )
                      ->last();
            $stack[] = ($prevTernary ?: $token)->TernaryOperator1;

            // Then, find
            // - the last token
            // - in the third expression
            // - of the last ternary expression
            // - encountered in this scope
            $current = $token;
            do {
                $until = $current->TernaryOperator2->EndExpression ?: $current;
            } while ($until !== $current &&
                ($current = $until->nextSibling())->IsTernaryOperator &&
                $current->TernaryOperator1 === $current);
            // And without breaking out of an unenclosed control structure body,
            // proceed to the end of the expression
            if (!$until->nextSibling()->IsTernaryOperator) {
                $until = $until->pragmaticEndOfExpression(true);
            }
        } elseif ($latest && $latest->BracketStack === $token->BracketStack) {
            if ($token->isStartOfExpression()) {
                $stack[] = $token;
            } elseif ($latest->isStartOfExpression()) {
                $stack[] = $latest;
            } elseif (!$prev->isStatementPrecursor() &&
                    $latest->prevCode()->isStatementPrecursor()) {
                $stack[] = $latest;
            }
        }

        // If a hanging indent has already been applied to a token with the same
        // stack, don't add it again
        if (in_array($stack, $token->IndentBracketStack, true)) {
            return;
        }

        // Add indentation for any unapplied 'hanging parents' of $parent to
        // ensure indentation accurately represents depth, e.g. in line 2 here:
        //
        //     if (!(($comment = $line->getLastOf(...TokenType::COMMENT)) &&
        //                 $comment->hasNewlineAfter()) ||
        //             $comment->hasNewline())
        //
        $until   = $until ?? $token->pragmaticEndOfExpression();
        $indent  = 0;
        $hanging = [];
        $parents = in_array($parent, $token->IndentParentStack, true)
            ? []
            : [$parent];
        $current = $parent;
        while (!($current = $current->parent())->IsNull && $current->IsHangingParent) {
            if (in_array($current, $token->IndentParentStack, true)) {
                continue;
            }
            $parents[] = $current;
            // Don't add indentation for this parent if it doesn't have any
            // hanging children
            $children = $current->innerSiblings()
                                ->filter(fn(Token $t) => $this->isHanging($t, true));
            if (!count($children)) {
                continue;
            }
            $indent++;
            $hanging[$current->Index] = 1;
            if ($current->IsOverhangingParent) {
                $indent++;
                $hanging[$current->Index]++;
            }
        }

        $indent++;
        if (!$token->prevCode()->isStatementPrecursor() &&
                $parent->IsOverhangingParent) {
            $indent++;
            $hanging[$parent->Index] = 1;
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

        if ($adjacent = $until->adjacentBeforeNewline()) {
            $until = $adjacent->pragmaticEndOfExpression();
        }

        $current = $token;
        do {
            // Allow multiple levels of hanging indentation within one parent:
            //
            // ```php
            // $a = $b->c(fn() =>
            //     $d &&
            //         $e)
            //     ?: $start;
            // ```
            if (array_key_exists($parent->Index, $current->OverhangingParents) &&
                    ($hanging[$parent->Index] ?? null)) {
                $current->OverhangingParents[$parent->Index] += $hanging[$parent->Index];
            }
            $current->HangingIndent      += $indent;
            $current->OverhangingParents += $hanging;
            if ($current !== $token) {
                $current->IndentBracketStack[] = $stack;
                $current->IndentStack[]        = $token;
                array_push($current->IndentParentStack, ...$parents);
            }
            if ($current === $until) {
                break;
            }
            $current = $current->next();
        } while (!$current->IsNull);
    }

    /**
     * @param array<int,int> $hanging
     */
    private function maybeCollapseOverhanging(Token $token, Token $until, array $hanging): void
    {
        $tokens = $until->withAdjacentBeforeNewline($token);
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
                    $next       = $current;
                    $nextIndent = 0;
                    do {
                        $next = $next->endOfLine()->next();
                        if ($next->IsNull) {
                            break;
                        }
                        $nextIndent = $this->effectiveIndent($next);
                    } while ($nextIndent === $indent &&
                        $next->Index <= $until->Index);
                    // Drop $indent and $nextIndent (if $next falls between
                    // $token and $until and this hanging indent hasn't already
                    // been collapsed) for comparison
                    $indent    -= $this->Formatter->TabSize;
                    $nextIndent = $next->IsNull ||
                        $next->Index > $until->Index ||
                        !($next->OverhangingParents[$index] ?? 0)
                            ? $nextIndent
                            : $nextIndent - $this->Formatter->TabSize;
                    if ($nextIndent === $indent && !$next->IsNull) {
                        break 3;
                    }
                    $current = $next;
                } while (!$current->IsNull && $current->Index <= $until->Index);
                $tokens->forEach(
                    function (Token $t) use ($index) {
                        if ($t->OverhangingParents[$index] ?? 0) {
                            $t->HangingIndent--;
                            $t->OverhangingParents[$index]--;
                        }
                    }
                );
            }
        }
    }

    private function isHanging(Token $token, bool $ignoreIndent = false): bool
    {
        // Ignore tokens aligned by other rules
        if ($token->AlignedWith ||
                $token->is([
                    ...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
                    ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
                    ...TokenType::NOT_CODE
                ])) {
            return false;
        }

        // $token is regarded as a continuation of $prev if:
        // - $token and $prev both have the same level of indentation
        // - $token is not an open brace (`{`) on its own line
        // - $prev is not a statement delimiter in a context where indentation
        //   is inherited from enclosing tokens
        $prev = $token->prevCode();
        if (!($prev->hasNewlineAfterCode() ||
                ($prev->is(T_START_HEREDOC) && !$token->prevCode(2)->hasNewlineAfterCode())) ||
            $token->isBrace() ||
            (!$ignoreIndent &&
                $this->indent($prev) !== $this->indent($token)) ||
            ($prev->isStatementPrecursor() &&
                !$prev->parent()->IsHangingParent)) {
            return false;
        }

        return true;
    }

    private function indent(Token $token): int
    {
        return $token->PreIndent + $token->Indent - $token->Deindent;
    }

    private function effectiveIndent(Token $token): int
    {
        // Ignore $token->LineUnpadding given its role in alignment
        return $token->indent() * $this->Formatter->TabSize
            + $token->LinePadding
            + $token->Padding;
    }
}
