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
 * Apply standard vertical spacing
 *
 * @api
 */
final class VerticalSpacing implements TokenRule
{
    use TokenRuleTrait;

    private bool $AlignChainsEnabled;
    /** @var array<int,Closure(Token): bool> */
    private array $HasNewline;
    /** @var array<int,Closure(Token): void> */
    private array $ApplyNewline;
    /** @var array<int,true> */
    private array $Seen;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 98,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return TokenIndex::merge(
            [
                \T_FOR => true,
                \T_OPEN_BRACE => true,
                \T_QUESTION => true,
            ],
            $idx->Chain,
            $idx->OperatorBooleanExceptNot,
        );
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->AlignChainsEnabled = $this->Formatter->Enabled[AlignChains::class]
            ?? false;

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

    /**
     * Apply the rule to the given tokens
     *
     * In expressions where one or more boolean operators have an adjacent
     * newline, newlines are added to other boolean operators of equal or lower
     * precedence.
     *
     * In `for` loops:
     *
     * - If an expression with multiple expressions breaks over multiple lines,
     *   newlines are added after comma-delimited expressions, and blank lines
     *   are added after semicolon-delimited expressions
     * - Otherwise, if an expression breaks over multiple lines, newlines are
     *   added after semicolon-delimited expressions
     * - Otherwise, if the second or third expression has a leading newline, a
     *   newline is added before the other
     * - Whitespace in empty expressions is suppressed
     *
     * Newlines are added before open braces that belong to top-level
     * declarations and anonymous classes declared over multiple lines.
     *
     * Newlines are added before both operators in ternary expressions where one
     * operator has a leading newline.
     *
     * In method chains where an object operator (`->` or `?->`) has a leading
     * newline, newlines are added before every object operator. If the
     * `AlignChains` rule is enabled and strict PSR-12 compliance is not, a
     * newline is not added before the first object operator in the chain.
     */
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
                $precedence = TokenUtil::getOperatorPrecedence($token);
                /** @var array<int,Token[]> */
                $byPrecedence = [$precedence => [$token]];
                $maxPrecedence = $precedence;

                foreach ($statement->withNextSiblings($token->EndStatement) as $t) {
                    if (
                        $t === $token
                        || !$this->Idx->OperatorBooleanExceptNot[$t->id]
                    ) {
                        continue;
                    }
                    $precedence = TokenUtil::getOperatorPrecedence($t);
                    $byPrecedence[$precedence][] = $t;
                    if (!($this->HasNewline[$t->id])($t)) {
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
            } elseif ($token->id === \T_FOR) {
                /** @var Token */
                $open = $token->NextCode;
                /** @var Token */
                $close = $open->CloseBracket;
                /** @var Token */
                $first = $open->Next;
                /** @var Token */
                $last = $close->Prev;

                $children = $open->children();
                $commas = $children->getAnyOf(\T_COMMA);
                $semicolons = $children->getAnyOf(\T_SEMICOLON);
                /** @var Token */
                $semi1 = $semicolons->first();
                /** @var Token */
                $second = $semi1->Next;
                /** @var Token */
                $semi2 = $semicolons->last();
                /** @var Token */
                $third = $semi2->Next;

                $expr1 = $first->collect($semi1);
                $expr2 = $second->collect($semi2);
                $expr3 = $third->collect($last);

                $hasNewline = false;
                $hasNewlineAndComma = false;
                foreach ([$expr1, $expr2, $expr3] as $expr) {
                    if ($expr->hasNewlineBetweenTokens()) {
                        $hasNewline = true;
                        if ($expr->hasOneOf(\T_COMMA)) {
                            $hasNewlineAndComma = true;
                            break;
                        }
                    }
                }
                if ($hasNewlineAndComma) {
                    $commas->applyWhitespace(Space::LINE_AFTER);
                    $semicolons->applyWhitespace(Space::BLANK_AFTER);
                } elseif ($hasNewline || $semicolons->tokenHasNewlineAfter()) {
                    $semicolons->applyWhitespace(Space::LINE_AFTER);
                }

                // Suppress whitespace in empty `for` loop expressions
                foreach ([$expr1, $expr2, $expr3] as $i => $expr) {
                    $count = $expr->count();
                    if ($i < 2 && $count === 1) {
                        $expr->applyWhitespace(Space::NONE_BEFORE);
                    } elseif ($i === 2 && $count === 0) {
                        $semi2->Whitespace |= Space::NONE_AFTER;
                    }
                }
            } elseif ($token->id === \T_OPEN_BRACE) {
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

                // Ignore:
                // - non-declarations
                // - `declare` blocks
                // - grouped imports and trait adaptations
                // - property hooks
                // - anonymous functions
                // - anonymous classes declared on one line
                $parts = $token->skipToStartOfDeclaration()->declarationParts();
                if (
                    !$parts->hasOneFrom($this->Idx->Declaration)
                    || ($first = $parts->first())->id === \T_DECLARE
                    || $first->id === \T_USE
                    || $token->inPropertyOrPropertyHook()
                    || $parts->last()
                             ->skipPrevSiblingFrom($this->Idx->Ampersand)
                             ->id === \T_FUNCTION
                    || ($first->id === \T_NEW && !$parts->hasNewlineBetweenTokens())
                ) {
                    continue;
                }

                $token->Whitespace |= Space::LINE_BEFORE;
            } elseif ($token->id === \T_QUESTION) {
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
            } else {
                if ($token !== $token->Data[TokenData::CHAIN_OPENED_BY]) {
                    continue;
                }

                $chain = $token->withNextSiblingsFrom($this->Idx->ChainPart)
                               ->getAnyFrom($this->Idx->Chain);

                if (
                    $chain->count() < 2
                    || !$chain->tokenHasNewlineBefore()
                ) {
                    continue;
                }

                if (
                    $this->AlignChainsEnabled
                    && !$this->Formatter->Psr12
                ) {
                    $chain = $chain->shift();
                }

                $chain->applyWhitespace(Space::LINE_BEFORE);
            }
        }
    }
}
