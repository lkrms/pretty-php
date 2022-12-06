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
        if (!$this->isHanging($token)) {
            return;
        }

        $stack  = $token->BracketStack;
        $latest = end($token->IndentStack);

        // Add `$latest` to `$stack` to differentiate between lines that
        // coincide with the start of a new expression and lines that continue
        // an expression started earlier, e.g. lines 2 and 3 here:
        //
        //     $iterator = new RecursiveDirectoryIterator($dir,
        //         FilesystemIterator::KEY_AS_PATHNAME |
        //             FilesystemIterator::CURRENT_AS_FILEINFO |
        //             FilesystemIterator::SKIP_DOTS);
        //
        if ($latest && $latest->BracketStack === $token->BracketStack &&
                $latest->prevCode()->isStatementPrecursor() &&
                !$token->prevCode()->isStatementPrecursor()) {
            $stack[] = $latest;
        }

        // If a hanging indent has already been applied to a token with the same
        // stack, don't add it again
        if (in_array($stack, $token->IndentBracketStack, true)) {
            return;
        }

        $parent = $token->parent();
        $add    = $parent->hasNewlineAfter() && $token->prevCode()->isStatementPrecursor()
            ? 0
            : ($token->prevCode()->isStatementPrecursor()
                ? 1
                // TODO: add `Token::startOfExpression()` or similar and check
                // for semicolons AND commas within `$token`'s expression, which
                // may not be bracketed (e.g the first ternary expression in
                // this very block)
                : ((!$latest || $latest->BracketStack !== $token->BracketStack) &&
                    !$parent->hasNewlineAfter() &&
                    ($parent->innerSiblings()->hasOneOf(',') ||
                        ($parent->nextSibling()->is('{') && !$parent->nextSibling()->hasNewlineBefore()))
                    ? 2
                    : 1));

        $current = $token;
        $until   = $token->endOfExpression();
        do {
            $current->HangingIndent += $add;
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
            !($token->isBrace() && $token->hasNewlineBefore()) &&
            !($prev->isStatementPrecursor() &&
                ($prev->parent()->isNull() || $prev->parent()->hasNewlineAfter()));
    }
}
