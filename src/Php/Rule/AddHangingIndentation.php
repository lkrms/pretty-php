<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * If the first token on a new line continues a statement from the previous one,
 * add a hanging indent
 *
 */
class AddHangingIndentation extends AbstractTokenRule
{
    /**
     * => [$token, $token->endOfExpression()]
     *
     * Entries represent a range of tokens where an 'overhanging' indent has
     * been applied in addition to a hanging indent.
     *
     * Used to collapse unnecessary overhanging indents.
     *
     * @var array<array{Token,Token}>
     */
    private $OverhangingTokens = [];

    /**
     * Parent index => indent (1 or 2) => true
     *
     * Entries represent token locations.
     *
     * @var array<int,array<int,true>>
     */
    private $ParentIndentsWithTokens = [];

    /**
     * Parent index => indent (1 or 2) => token array
     *
     * Entries represent indentation levels beyond which there are one or more
     * tokens.
     *
     * Used to collapse empty indentation levels.
     *
     * @var array<int,array<int,Token[]>>
     */
    private $ParentIndentsBeforeTokens = [];

    public function __invoke(Token $token, int $stage): void
    {
        if ($token->isOneOf('(', '[') && !$token->hasNewlineAfter()) {
            $token->IsHangingParent     = true;
            $token->IsOverhangingParent =
                // Does it have delimited values? (e.g. `list($var1, $var2)`)
                $token->innerSiblings()->hasOneOf(',') ||
                // Delimited expressions? (e.g. `for (expr; expr; expr)`)
                ($token->is('(') && $token->innerSiblings()->hasOneOf(';')) ||
                // A subsequent statement or block? (e.g. `if (expr) statement`)
                $token->endOfStatement()->Index > $token->ClosedBy->endOfLine()->Index;
        }

        if (!$token->isCode() || !$this->isHanging($token)) {
            return;
        }

        $stack  = $token->BracketStack;
        $latest = end($token->IndentStack);
        $prev   = $token->prevCode();

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
        if ($latest && $latest->BracketStack === $token->BracketStack) {
            if (!$prev->isStatementPrecursor() &&
                    $latest->prevCode()->isStatementPrecursor()) {
                $stack[] = $latest;
            } elseif ($token->isTernaryOperator() &&
                    !$latest->isTernaryOperator()) {
                $stack[] = $token;
            }
        }

        $parent  = $token->parent();
        $current = $parent->parent();
        $add     = 0;
        while (!$current->isNull()) {
            if ($current->IsHangingParent) {
                $add++;
                $indent = 1;
                if ($current->IsOverhangingParent) {
                    $add++;
                    $indent++;
                }
                $this->ParentIndentsBeforeTokens[$current->Index][$indent][] = $token;
            }
            $current = $current->parent();
        }

        // If a hanging indent has already been applied to a token with the same
        // stack, don't add it again
        if (in_array($stack, $token->IndentBracketStack, true)) {
            return;
        }

        if ($token->HangingParentsApplied) {
            $add = 0;
        }

        $current = $token;
        $until   = $token->endOfExpression();
        $indent  = 0;
        if ($token->prevCode()->isStatementPrecursor()) {
            if (!$parent->hasNewlineAfter()) {
                $add++;
                $indent++;
            }
        } else {
            $add++;
            $indent++;
            if ($parent->IsOverhangingParent) {
                $add++;
                $indent++;
                $this->OverhangingTokens[] = [$token, $until];
            }
        }
        $this->ParentIndentsWithTokens[$parent->Index][$indent] = true;

        do {
            $current->HangingIndent        += $add;
            $current->HangingParentsApplied = true;
            if ($current !== $token) {
                $current->IndentBracketStack[] = $stack;
                $current->IndentStack[]        = $token;
            }
            if ($current === $until) {
                break;
            }
            $current = $current->next();
        } while (!$current->isNull());
    }

    public function afterTokenLoop(): void
    {
        foreach ($this->ParentIndentsBeforeTokens as $parentIndex => $tokensByIndent) {
            foreach ([1, 2] as $indent) {
                if (!($this->ParentIndentsWithTokens[$parentIndex][$indent] ?? null)) {
                    foreach (($tokensByIndent[$indent] ?? []) as $token) {
                        $token->HangingIndent--;
                    }
                }
            }
        }

        /**
         * @var Token $token
         * @var Token $until
         */
        foreach ($this->OverhangingTokens as [$token, $until]) {
            if (!$token->HangingIndent) {
                continue;
            }
            $indent     = $token->indent();
            $nextLine   = $token->endOfLine()->next();
            $nextIndent = $nextLine->indent();
            // If $nextLine falls between $token and $until, adjust the
            // calculation below accordingly
            $adjust     = $nextLine->Index <= $until->Index;
            // The purpose of 'overhanging' indents is to visually separate
            // distinct blocks of code that would otherwise run together, but
            // this is unnecessary when indentation increases on the next line
            if ($nextIndent > $indent ||
                    $indent - $nextIndent > ($adjust ? 0 : 1)) {
                $token->collect($until)->forEach(fn(Token $t) => $t->HangingIndent--);
            }
        }
    }

    private function isHanging(Token $token): bool
    {
        if ($token->is(T_CLOSE_TAG)) {
            return false;
        }
        $prev = $token->prevCode();
        if ($prev === $token->prev()) {
            if (!$prev->hasNewlineAfter()) {
                return false;
            }
        } else {
            if (!$prev->collect($token)->hasInnerNewline()) {
                return false;
            }
        }

        // $token is regarded as a continuation of $prev if:
        // - $token and $prev both have the same level of indentation;
        // - $token is not an opening brace (`{`) on its own line; and
        // - $prev is not a statement delimiter in a context where indentation
        //   is inherited from enclosing tokens
        return ($prev->Indent - $prev->Deindent) === ($token->Indent - $token->Deindent) &&
            ($token->isTernaryOperator() ||
                (!($token->isBrace() && $token->hasNewlineBefore()) &&
                    !($prev->isStatementPrecursor() &&
                        ($prev->parent()->isNull() || $prev->parent()->hasNewlineAfter())) &&
                    !($token->isOneOf(T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR) &&
                        in_array(AlignChainedCalls::class, $this->Formatter->Rules) &&
                        $token->hasNewlineBefore())));
    }
}
