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

    /**
     * => [$token, $token->endOfExpression()]
     *
     * Entries represent a range of tokens where an 'overhanging' indent has
     * been applied in addition to a hanging indent.
     *
     * Used to collapse unnecessary overhanging indents.
     *
     * @var array<array{0:Token,1:Token}>
     */
    private $OverhangingTokens = [];

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

        if (!$token->isCode() || !$this->isHanging($token)) {
            return;
        }

        $stack  = $token->BracketStack;
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

        $current = $token;
        $until   = $token->endOfExpression();
        $indent  = 0;
        if ($token->prevCode()->isStatementPrecursor()) {
            if (!$parent->hasNewlineAfterCode()) {
                $indent++;
            }
        } else {
            $indent++;
            if ($parent->IsOverhangingParent) {
                $indent++;
                $this->OverhangingTokens[$token->Index] = [$token, $until];
            }
        }

        do {
            $current->HangingIndent += $indent;
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
        /**
         * @var Token $token
         * @var Token $until
         */
        foreach ($this->OverhangingTokens as [$token, $until]) {
            if (!$token->HangingIndent) {
                continue;
            }
            $indent     = $token->indent();
            $next       = $token;
            $nextIndent = 0;
            do {
                $next = $next->endOfLine()->next();
                if ($next->isNull()) {
                    break;
                }
                $nextIndent = $next->indent();
            } while ($nextIndent === $indent);
            // If $nextLine falls between $token and $until, adjust the
            // calculation below accordingly
            $adjust = !$next->isNull() && $next->Index <= $until->Index;
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
        if (!$prev->hasNewlineAfterCode()) {
            return false;
        }

        // $token is regarded as a continuation of $prev if:
        // - $token and $prev both have the same level of indentation;
        // - $token is not an opening brace (`{`) on its own line; and
        // - $prev is not a statement delimiter in a context where indentation
        //   is inherited from enclosing tokens
        // - $token is not subject to alignment by AlignChainedCalls
        return ($prev->Indent - $prev->Deindent) === ($token->Indent - $token->Deindent) &&
            ($token->isTernaryOperator() ||
                (!($token->isBrace() && $token->hasNewlineBefore()) &&
                    !($prev->isStatementPrecursor() &&
                        ($prev->parent()->isNull() ||
                            $prev->parent()->hasNewlineAfterCode())) &&
                    !($token->isOneOf(T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR) &&
                        in_array(AlignChainedCalls::class, $this->Formatter->Rules) &&
                        $token->hasNewlineBefore())));
    }
}
