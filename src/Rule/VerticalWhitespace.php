<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;
use Lkrms\PrettyPHP\TokenUtil;
use Closure;

/**
 * Apply sensible vertical spacing
 *
 * - Propagate newlines adjacent to boolean operators to others of equal or
 *   lower precedence in the same statement
 * - If an expression in a `for` loop breaks over multiple lines, add a newline
 *   after each comma-delimited expression and a blank line between each
 *   semicolon-delimited expression
 * - If the second or third expression in a `for` loop is at the start of a
 *   line, add a newline before the other
 * - Suppress whitespace in empty `for` loop expressions
 * - Add a newline before an open brace that is part of a top-level declaration
 *   or an anonymous class declared over multiple lines
 * - If one ternary operator is at the start of a line, add a newline before the
 *   other
 * - If an object operator (`->` or `?->`) is at the start of a line, add a
 *   newline before other object operators in the same chain
 *
 * @api
 */
final class VerticalWhitespace implements TokenRule
{
    use TokenRuleTrait;

    private bool $AlignChainsEnabled;
    /** @var array<int,Closure(Token): bool> */
    private array $HasNewline;
    /** @var array<int,Closure(Token): void> */
    private array $ApplyNewline;
    /** @var array<int,true> */
    private array $Seen;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 98,
        ][$method] ?? null;
    }

    public static function getTokens(TokenIndex $idx): array
    {
        return TokenIndex::merge(
            TokenIndex::get(
                \T_FOR,
                \T_OPEN_BRACE,
                \T_QUESTION,
            ),
            $idx->Chain,
            $idx->OperatorBooleanExceptNot,
        );
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->AlignChainsEnabled = $this->Formatter->Enabled[AlignChains::class] ?? false;

        $hasNewlineBefore = fn(Token $t): bool => $t->hasNewlineAfterPrevCode();
        $hasNewlineAfter = fn(Token $t): bool => $t->hasNewlineBeforeNextCode();
        $applyNewlineBefore = function (Token $t): void {
            $sol = $t->startOfLine();
            /** @var Token */
            $prev = $t->Prev;
            /** @var Token */
            $next = $t->Next;
            // Suppress newlines after standalone close brackets, e.g. `) &&`
            // where `)` is at the start of a line
            if (
                ($prev->index >= $sol->index ? $sol : $prev->startOfLine())
                    ->collect($prev)
                    ->hasOneNotFrom($this->Idx->CloseBracket)
                || $next
                       ->collect($next->endOfLine())
                       ->hasOneNotFrom($this->Idx->OpenBracketOrNot)
            ) {
                $t->Whitespace |= Space::LINE_BEFORE;
            } else {
                $t->Whitespace |= Space::NO_LINE_BEFORE;
            }
        };
        $applyNewlineAfter = function (Token $t): void {
            $eol = $t->endOfLine();
            /** @var Token */
            $next = $t->Next;
            /** @var Token */
            $prev = $t->Prev;
            // Suppress newlines before standalone open brackets, e.g. `&& (`
            // where `(` is at the end of a line
            if (
                $next->collect($next->index <= $eol->index ? $eol : $next->endOfLine())
                     ->hasOneNotFrom($this->Idx->OpenBracketOrNot)
                || $prev->startOfLine()
                        ->collect($prev)
                        ->hasOneNotFrom($this->Idx->CloseBracket)
            ) {
                $t->Whitespace |= Space::LINE_AFTER;
            } else {
                $t->Whitespace |= Space::NO_LINE_AFTER;
            }
        };

        foreach (array_keys(array_filter($this->Idx->OperatorBooleanExceptNot)) as $id) {
            if (
                $this->Idx->AllowNewlineBefore[$id]
                || !$this->Idx->AllowNewlineAfter[$id]
            ) {
                $this->HasNewline[$id] = $hasNewlineBefore;
                $this->ApplyNewline[$id] = $applyNewlineBefore;
            } else {
                $this->HasNewline[$id] = $hasNewlineAfter;
                $this->ApplyNewline[$id] = $applyNewlineAfter;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Seen = [];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Propagate newlines adjacent to boolean operators to others of
            // equal or lower precedence in the same statement
            if ($this->Idx->OperatorBooleanExceptNot[$token->id]) {
                /** @var Token */
                $statement = $token->Statement;

                // Ignore statements already processed and tokens with no
                // adjacent newline
                if (
                    isset($this->Seen[$statement->index])
                    || !($this->HasNewline[$token->id])($token)
                ) {
                    continue;
                }

                $this->Seen[$statement->index] = true;

                // Get the statement's boolean operators and find the
                // highest-precedence operator with an adjacent newline
                /** @var array<int,Token[]> */
                $byPrecedence = [];
                $maxPrecedence = TokenUtil::getOperatorPrecedence($token);

                foreach ($statement->withNextSiblings($token->EndStatement) as $t) {
                    if (!$this->Idx->OperatorBooleanExceptNot[$t->id]) {
                        continue;
                    }
                    $precedence = TokenUtil::getOperatorPrecedence($t);
                    $byPrecedence[$precedence][] = $t;
                    if ($t === $token || !($this->HasNewline[$t->id])($t)) {
                        continue;
                    }
                    // Lower numbers = higher precedence
                    $maxPrecedence = min($maxPrecedence, $precedence);
                }

                foreach ($byPrecedence as $precedence => $tokens) {
                    if ($precedence >= $maxPrecedence) {
                        foreach ($tokens as $t) {
                            ($this->ApplyNewline[$t->id])($t);
                        }
                    }
                }

                continue;
            }

            if ($token->id === \T_FOR) {
                assert(
                    $token->NextCode
                    && $token->NextCode->Next
                    && $token->NextCode->CloseBracket
                    && $token->NextCode->CloseBracket->Prev
                );

                $children = $token->NextCode->children();
                $commas = $children->getAnyOf(\T_COMMA);
                $semicolons = $children->getAnyOf(\T_SEMICOLON);
                $semi1 = $semicolons->first();
                $semi2 = $semicolons->last();

                assert($semi1 && $semi1->Next && $semi2 && $semi2->Next);

                $expr1 = $token->NextCode->Next->withNextSiblings($semi1);
                $expr2 = $semi1->Next->withNextSiblings($semi2);
                $expr3 = $semi2->Next->withNextSiblings($token->NextCode->CloseBracket->Prev);

                // If an expression in a `for` loop breaks over multiple lines,
                // add a newline after each comma-delimited expression and a
                // blank line between each semicolon-delimited expression
                if (
                    $expr1->hasNewlineBetweenTokens()
                    || $expr2->hasNewlineBetweenTokens()
                    || $expr3->hasNewlineBetweenTokens()
                ) {
                    $commas->applyWhitespace(Space::LINE_AFTER);
                    $semicolons->applyWhitespace(Space::BLANK_AFTER);
                } elseif ($semicolons->tokenHasNewlineAfter()) {
                    // If the second or third expression in a `for` loop is at
                    // the start of a line, add a newline before the other
                    $semicolons->applyWhitespace(Space::LINE_AFTER);
                }

                // Suppress whitespace in empty `for` loop expressions
                foreach ([[$expr1, 1], [$expr2, 1], [$expr3, 0]] as [$expr, $emptyCount]) {
                    if ($expr->count() === $emptyCount) {
                        if ($emptyCount) {
                            $expr->applyWhitespace(Space::NONE_BEFORE);
                        } else {
                            $semi2->Whitespace |= Space::NONE_AFTER;
                        }
                    }
                }

                continue;
            }

            // Add a newline before an open brace that is part of a top-level
            // declaration or an anonymous class declared over multiple lines
            if ($token->id === \T_OPEN_BRACE) {
                if (
                    $this->Formatter->OneTrueBraceStyle
                    || !($token->Flags & TokenFlag::STRUCTURAL_BRACE)
                    || (
                        $token->Next
                        && $token->Next->id === \T_CLOSE_BRACE
                        && !$token->hasNewlineAfter()
                    )
                ) {
                    continue;
                }

                $parts = $token->skipToStartOfDeclaration()->declarationParts();
                if (
                    // Exclude non-declarations
                    !$parts->hasOneFrom($this->Idx->Declaration)
                    // Exclude `declare` blocks
                    || ($last = $parts->last())->id === \T_DECLARE
                    // Exclude grouped imports and trait adaptations
                    || ($start = $parts->first())->id === \T_USE
                    // Exclude property hooks
                    || $token->inPropertyOrPropertyHook()
                    // Exclude anonymous functions
                    || $last->skipPrevSiblingFrom($this->Idx->Ampersand)->id === \T_FUNCTION
                    // Exclude anonymous classes declared on one line
                    || ($start->id === \T_NEW && !$parts->hasNewlineBetweenTokens())
                ) {
                    continue;
                }

                $token->Whitespace |= Space::LINE_BEFORE;

                continue;
            }

            // If one ternary operator is at the start of a line, add a newline
            // before the other
            if ($token->id === \T_QUESTION) {
                if (!($token->Flags & TokenFlag::TERNARY_OPERATOR)) {
                    continue;
                }

                $other = $token->Data[TokenData::OTHER_TERNARY_OPERATOR];

                if ($other === $token->Next) {
                    continue;
                }

                $op1Newline = $token->hasNewlineBefore();
                $op2Newline = $other->hasNewlineBefore();
                if ($op1Newline && !$op2Newline) {
                    $other->Whitespace |= Space::LINE_BEFORE;
                } elseif (!$op1Newline && $op2Newline) {
                    $token->Whitespace |= Space::LINE_BEFORE;
                }

                continue;
            }

            // if ($this->Idx->Chain[$token->id]) {

            // If an object operator (`->` or `?->`) is at the start of a line,
            // add a newline before other object operators in the same chain
            if ($token !== $token->Data[TokenData::CHAIN_OPENED_BY]) {
                continue;
            }

            $chain = $token->withNextSiblingsFrom($this->Idx->ChainPart)
                           ->getAnyFrom($this->Idx->Chain);

            if (
                $chain->count() < 2
                || !$chain->find(fn(Token $t) => $t->hasNewlineBefore())
            ) {
                continue;
            }

            // Leave the first object operator alone if chain alignment is
            // enabled and strict PSR-12 compliance isn't
            if ($this->AlignChainsEnabled && !$this->Formatter->Psr12) {
                $chain = $chain->shift();
            }

            $chain->applyWhitespace(Space::LINE_BEFORE);

            // }
        }
    }
}
