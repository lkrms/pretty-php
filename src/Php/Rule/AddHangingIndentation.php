<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;

/**
 * If the first token on a new line continues a statement from the previous
 * line, add a hanging indent
 *
 */
class AddHangingIndentation extends AbstractTokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->isOneOf('(', '[') && !$token->hasNewlineAfter()) {
            if ($token->innerSiblings()->hasOneOf(',') ||
                    ($token->is('(') && ($token->nextSibling()->is('{') ||
                        $token->innerSiblings()->hasOneOf(';')))) {
                /** @var Token $t */
                foreach ($token->inner() as $t) {
                    if ($t->isOpenBracket() && $t->hasNewlineAfter()) {
                        break;
                    }
                    $t->OverhangingIndent++;
                }
            }
        }

        if (!$this->isHanging($token)) {
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
        // `$stack`, as seen on line 82 at the time of writing:
        //
        //     $current->HangingIndent +=
        //        is_null($add)
        //            ? 1 + $this->claimOverhang($current)
        //            : $add;
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

        // If a hanging indent has already been applied to a token with the same
        // stack, don't add it again
        if (in_array($stack, $token->IndentBracketStack, true)) {
            return;
        }

        $parent = $token->parent();
        $add    = $parent->hasNewlineAfter() && $prev->isStatementPrecursor()
            ? 0
            : ($prev->isStatementPrecursor() ||
                ($latest && $latest->BracketStack === $token->BracketStack)
                    ? 1
                    : null);

        $current = $token;
        $until   = $token->endOfExpression();
        do {
            $current->HangingIndent +=
                is_null($add)
                    ? 1 + $this->claimOverhang($current)
                    : $add;
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
                        ($prev->parent()->isNull() || $prev->parent()->hasNewlineAfter()))));
    }

    private function claimOverhang(Token $token): int
    {
        if (!$token->OverhangingIndent) {
            return 0;
        }
        $token->OverhangingIndent--;

        return 1;
    }
}
