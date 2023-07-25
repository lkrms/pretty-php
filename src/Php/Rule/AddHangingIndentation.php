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
 */
final class AddHangingIndentation implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    private const NO_INDENT = 0;
    private const NORMAL_INDENT = 1;
    private const OVERHANGING_INDENT = 2;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 800;

            default:
                return null;
        }
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($this->TypeIndex->StandardOpenBracket[$token->id]) {
                $token->HangingIndentParentType =
                    $token->hasNewlineAfterCode()
                        ? ($token->IsListParent ||
                            ($token->id === T_OPEN_BRACE && $token->isStructuralBrace())
                                ? self::NORMAL_INDENT
                                : self::NO_INDENT)
                        : ($token->IsListParent ||
                            ($token->id === T_OPEN_BRACE && $token->isStructuralBrace()) ||
                            ($token->id !== T_OPEN_BRACE && $token->adjacent())
                                ? self::OVERHANGING_INDENT
                                : self::NORMAL_INDENT);
            }

            $parent = end($token->BracketStack);
            if ($token->IsCode &&
                    $parent &&
                    $parent === $token->_prevCode &&
                    $token !== $parent->ClosedBy &&
                    $parent->HangingIndentParentType === self::NO_INDENT) {
                $current = $token;
                $stack = [$token->BracketStack];
                $stack[] = $token;
                do {
                    $current->IndentBracketStack[] = $stack;
                    $current->IndentStack[] = $token;
                    $current->IndentParentStack[] = $parent;
                } while (($current = $current->_next) &&
                    $current !== $parent->ClosedBy);
                continue;
            }

            // Ignore tokens aligned by other rules
            if (!$token->IsCode ||
                    !$token->_prevCode ||
                    $token->AlignedWith ||
                    $this->TypeIndex->CloseBracketOrEndAltSyntax[$token->id] ||
                    $this->TypeIndex->HasStatement[$token->id] ||
                    $this->TypeIndex->NotCode[$token->id]) {
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
            $prev = $token->_prevCode;
            if (!$prev->hasNewlineAfterCode() &&
                !(($heredocIndent = $this->Formatter->getHeredocIndent())
                    & (HeredocIndent::MIXED | HeredocIndent::HANGING) &&
                    $prev->id === T_START_HEREDOC &&
                    ($heredocIndent & HeredocIndent::HANGING ||
                        ($prev->_prevCode &&
                            !$prev->AlignedWith &&
                            !$prev->_prevCode->hasNewlineAfterCode())))) {
                continue;
            }

            // Optionally suppress hanging indentation between conditional
            // expressions in `match`
            if (!$this->Formatter->HangingMatchIndents &&
                    $prev->id === T_COMMA &&
                    $parent &&
                    $parent->id === T_OPEN_BRACE &&
                    $parent->prevSibling(2)->id === T_MATCH) {
                continue;
            }

            // Ignore open braces, statements that inherit indentation from
            // enclosing brackets, and lines with different indentation levels
            // from the previous line
            if ($token->id === T_OPEN_BRACE ||
                    ($token->Statement === $token &&
                        (!$parent ||
                            $parent->HangingIndentParentType !== self::OVERHANGING_INDENT)) ||
                    $this->indent($prev) !== $this->indent($token)) {
                continue;
            }

            $stack = [$token->BracketStack];
            $latest = end($token->IndentStack);
            unset($until);

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
                    $token->prevSiblingsUntil(
                              fn(Token $t) =>
                                  $t->Statement !== $token->Statement
                          )
                          ->filter(
                              fn(Token $t) =>
                                  $t->IsTernaryOperator &&
                                      $t->TernaryOperator1 === $t &&
                                      $t->TernaryOperator2->Index < $token->TernaryOperator1->Index
                          )
                          ->last();
                $stack[] = $prevTernary ?: $token->TernaryOperator1;

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
            } elseif ($token->ChainOpenedBy) {
                $stack[] = $token->ChainOpenedBy;
            } elseif ($token->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT]) ||
                    $token->prevSibling()->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT])) {
                $stack[] = $token->Expression;
            } elseif ($latest && $latest->BracketStack === $token->BracketStack) {
                if ($token->isStartOfExpression()) {
                    $stack[] = $token;
                } elseif ($latest->isStartOfExpression()) {
                    $stack[] = $latest;
                } elseif ($latest->id === T_DOUBLE_ARROW) {
                    $stack[] = $latest;
                } elseif (!$prev->precedesStatement() &&
                        $latest->prevCode()->precedesStatement()) {
                    $stack[] = $latest;
                }
            }

            // If a hanging indent has already been applied to a token with the same
            // stack, don't add it again
            if (in_array($stack, $token->IndentBracketStack, true)) {
                continue;
            }

            // Add indentation for any unapplied 'hanging parents' of $parent to
            // ensure indentation accurately represents depth, e.g. in line 2 here:
            //
            //     if (!(($comment = $line->getLastOf(...TokenType::COMMENT)) &&
            //                 $comment->hasNewlineAfter()) ||
            //             $comment->hasNewline())
            //
            $until = $until ?? $token->pragmaticEndOfExpression(true);
            $indent = 0;
            $hanging = [];
            $parents = !$parent || in_array($parent, $token->IndentParentStack, true)
                ? []
                : [$parent];
            $current = $parent;
            while ($current && ($current = end($current->BracketStack)) && $current->HangingIndentParentType !== self::NO_INDENT) {
                if (in_array($current, $token->IndentParentStack, true)) {
                    continue;
                }
                $parents[] = $current;
                $indent++;
                $hanging[$current->Index] = 1;
                if ($current->HangingIndentParentType === self::OVERHANGING_INDENT) {
                    $indent++;
                    $hanging[$current->Index]++;
                }
            }

            $indent++;
            if ($parent && $parent->HangingIndentParentType === self::OVERHANGING_INDENT &&
                    !$prev->precedesStatement()) {
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
                if ($parent && array_key_exists($parent->Index, $current->OverhangingParents) &&
                        ($hanging[$parent->Index] ?? null)) {
                    $current->OverhangingParents[$parent->Index] += $hanging[$parent->Index];
                }
                $current->HangingIndent += $indent;
                $current->OverhangingParents += $hanging;
                if ($current !== $token) {
                    $current->IndentBracketStack[] = $stack;
                    $current->IndentStack[] = $token;
                    array_push($current->IndentParentStack, ...$parents);
                }
                if ($current === $until) {
                    break;
                }
                $current = $current->next();
            } while (!$current->IsNull);
        }
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
                    $next = $current;
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
                    $unit = 1;
                    $indent -= $unit;
                    $nextIndent = $next->IsNull ||
                        $next->Index > $until->Index ||
                        !($next->OverhangingParents[$index] ?? 0)
                            ? $nextIndent
                            : $nextIndent - $unit;
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
