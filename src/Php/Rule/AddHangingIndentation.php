<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * If the first token on a new line continues a statement from the previous one,
 * add a hanging indent
 *
 */
class AddHangingIndentation implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isOneOf('(', '[', '{') && !$token->hasNewlineAfterCode()) {
            $token->IsHangingParent     = true;
            $token->IsOverhangingParent =
                // Does it have delimited values? (e.g. `list(var, var)`)
                $token->innerSiblings()->hasOneOf(',') ||
                // Delimited expressions? (e.g. `for (expr; expr; expr)`)
                ($token->is('(') && $token->innerSiblings()->hasOneOf(';')) ||
                // A subsequent statement or block? (e.g. `if (expr) statement`)
                $token->hasAdjacentBlock();
        }

        if (!$token->isCode() ||
                $token->is(T_CLOSE_TAG) ||
                !$this->isHanging($token)) {
            return;
        }

        $stack  = [$token->BracketStack];
        $latest = end($token->IndentStack);
        $prev   = $token->prevCode();
        $parent = $token->parent();

        // Add `$latest` to `$stack` to differentiate between lines that
        // coincide with the start of a new expression and lines that continue
        // an expression started earlier, e.g. lines 2 and 3 here:
        //
        //     $iterator = new RecursiveDirectoryIterator($dir,
        //         FilesystemIterator::KEY_AS_PATHNAME |
        //             FilesystemIterator::CURRENT_AS_FILEINFO |
        //             FilesystemIterator::SKIP_DOTS);
        //
        // Similarly, differentiate between ternary operators and earlier lines
        // with the same bracket stack by adding the first indented operator to
        // `$stack`, e.g.:
        //
        //     return is_string($contents)
        //         ? $contents
        //         : json_encode($contents, JSON_PRETTY_PRINT);
        //
        // In the same way, differentiate between the start of a ternary
        // expression and a continued one, e.g. lines 4 and 5 here:
        //
        //     fn($a, $b) =>
        //         $a === $b
        //             ? 0
        //             : $a <=>
        //                 $b;
        //
        if ($latest && $latest->BracketStack === $token->BracketStack) {
            if (!$prev->isStatementPrecursor() &&
                    $latest->prevCode()->isStatementPrecursor()) {
                $stack[] = $latest;
            } elseif ($token->isTernaryOperator() &&
                    !$latest->isTernaryOperator()) {
                $stack[] = $token;
            } elseif (!$token->isTernaryOperator() &&
                    $latest->isTernaryOperator()) {
                $stack[] = $token;
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
        $until   = $token->endOfExpression();
        $indent  = 0;
        $parents = in_array($parent, $token->IndentParentStack, true)
            ? []
            : [$parent];
        $current = $parent;
        while (!($current = $current->parent())->isNull() && $current->IsHangingParent) {
            if (!in_array($current, $token->IndentParentStack, true)) {
                $parents[] = $current;
                // Don't add indentation for this parent if it doesn't have any
                // hanging children
                $children  = $current->innerSiblings()->filter(fn(Token $t) => $this->isHanging($t, true));
                if (!count($children)) {
                    continue;
                }
                $indent++;
                if ($current->IsOverhangingParent &&
                    $children->find(fn(Token $t) =>
                        !$t->prevCode()->isStatementPrecursor())) {
                    $indent++;
                }
            }
        }

        if ($token->prevCode()->isStatementPrecursor()) {
            $indent++;
        } else {
            $indent++;
            if ($parent->IsOverhangingParent) {
                $indent++;
            }
        }

        if ($indent > 1) {
            $this->Formatter->registerCallback($this, $token, fn() => $this->maybeCollapseOverhanging($token, $until, $indent - 1), 800);
        }

        $current = $token;
        do {
            $current->HangingIndent += $indent;
            if ($current !== $token) {
                $current->IndentBracketStack[] = $stack;
                $current->IndentStack[]        = $token;
                array_push($current->IndentParentStack, ...$parents);
            }
            if ($current === $until) {
                break;
            }
            $current = $current->next();
        } while (!$current->isNull());
    }

    private function maybeCollapseOverhanging(Token $token, Token $until, int $overhang): void
    {
        while ($token->HangingIndent && $overhang) {
            $indent     = $token->indent() + $this->paddingIndent($token);
            $next       = $token;
            $nextIndent = 0;
            do {
                $next = $next->endOfLine()->next();
                if ($next->isNull()) {
                    break;
                }
                $nextIndent = $next->indent() + $this->paddingIndent($next);
            } while ($nextIndent === $indent);
            // Drop $indent and $nextLine (if it falls between $token and
            // $until) for comparison
            $indent--;
            $nextIndent = $next->isNull() || $next->Index > $until->Index
                ? $nextIndent
                : $nextIndent - 1;
            // The purpose of overhanging indents is to visually separate
            // distinct blocks of code that would otherwise run together,
            // but this is unnecessary when indentation changes on the next
            // line
            if ($nextIndent === $indent && !$next->isNull()) {
                break;
            }
            $token->collect($until)->forEach(fn(Token $t) => $t->HangingIndent--);
            $overhang--;
        }
    }

    private function isHanging(Token $token, bool $ignoreIndent = false): bool
    {
        $prev = $token->prevCode();

        // $token is regarded as a continuation of $prev if:
        // - $token and $prev both have the same level of indentation
        // - $token is not an opening brace (`{`) on its own line
        // - $prev is not a statement delimiter in a context where indentation
        //   is inherited from enclosing tokens
        // - $token is not subject to alignment by AlignChainedCalls
        if (!$prev->hasNewlineAfterCode() ||
                (!$ignoreIndent && $this->indent($prev) !== $this->indent($token)) ||
                $token->ChainOpenedBy ||
                $token->isBrace() ||
                ($prev->isStatementPrecursor() && !$prev->parent()->IsHangingParent)) {
            return false;
        }

        return true;
    }

    private function indent(Token $token): int
    {
        return $token->PreIndent + $token->Indent - $token->Deindent;
    }

    private function paddingIndent(Token $token): int
    {
        return (int) (($token->LinePadding + $token->Padding)
            / strlen($this->Formatter->SoftTab));
    }
}
