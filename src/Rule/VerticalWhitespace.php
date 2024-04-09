<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Core\Utility\Arr;
use Closure;

/**
 * Apply sensible vertical spacing
 *
 * - Propagate newlines adjacent to boolean operators to others of equal or
 *   lower precedence in the same statement
 * - If an expression in a `for` loop breaks over multiple lines, add a newline
 *   after each comma-delimited expression and a blank line between each
 *   semicolon-delimited expression
 * - Add a newline before an open brace that is part of a top-level declaration
 *   or an anonymous class declared over multiple lines
 * - If the second or third expression in a `for` loop is at the start of a
 *   line, add a newline before the other
 * - Suppress whitespace in empty `for` loop expressions
 * - If one ternary operator is at the start of a line, add a newline before the
 *   other
 * - If an object operator (`->` or `?->`) is at the start of a line, add a
 *   newline before other object operators in the same chain
 *
 * @api
 */
final class VerticalWhitespace implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    private const BOOLEAN_MAP = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => \T_AND,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => \T_AND,
    ];

    private const BOOLEAN_PRECEDENCE = [
        \T_AND => 0,
        \T_XOR => 1,
        \T_OR => 2,
        \T_BOOLEAN_AND => 3,
        \T_BOOLEAN_OR => 4,
        \T_LOGICAL_AND => 5,
        \T_LOGICAL_XOR => 6,
        \T_LOGICAL_OR => 7,
    ];

    /**
     * @var array<int,bool>
     */
    private array $CommaIndex;

    /**
     * @var array<int,bool>
     */
    private array $SemicolonIndex;

    /**
     * @var array<int,bool>
     */
    private array $OpenBracketOrNotIndex;

    /**
     * @var array<int,Closure(Token): bool>
     */
    private array $BooleanHasLineBreakClosure;

    /**
     * @var array<int,Closure(Token): void>
     */
    private array $ApplyBooleanLineBreakClosure;

    /**
     * @var array<int,null>
     */
    private array $EmptyBooleansByType;

    /**
     * @var array<int,true>
     */
    private array $Seen;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 98;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_FOR,
            \T_OPEN_BRACE,
            \T_QUESTION,
            ...TokenType::CHAIN,
            ...TokenType::OPERATOR_BOOLEAN_EXCEPT_NOT,
        ];
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Seen = [];

        if (isset($this->EmptyBooleansByType)) {
            return;
        }

        /** @var array<int,Closure(Token): bool> */
        $hasLineBreak = [];
        /** @var array<int,Closure(Token): void> */
        $applyLineBreak = [];
        $booleanTypes = array_keys(self::BOOLEAN_PRECEDENCE);
        foreach ($booleanTypes as $type) {
            if (
                $this->TypeIndex->PreserveNewlineBefore[$type]
                || !$this->TypeIndex->PreserveNewlineAfter[$type]
            ) {
                $hasLineBreak[$type] =
                    fn(Token $token): bool => $token->hasNewlineBefore();
                $applyLineBreak[$type] =
                    function (Token $token): void {
                        $startOfLine = $token->startOfLine();
                        if (
                            $startOfLine === $token
                            || $startOfLine->collect($token->Prev)->hasOneNotFrom($this->TypeIndex->CloseBracket)
                        ) {
                            $token->WhitespaceBefore |= WhitespaceType::LINE;
                        }
                    };
            } else {
                $hasLineBreak[$type] =
                    fn(Token $token): bool => $token->hasNewlineBeforeNextCode();
                $applyLineBreak[$type] =
                    function (Token $token): void {
                        $endOfLine = $token->endOfLine();
                        if (
                            $endOfLine === $token
                            || $token->Next->collect($endOfLine)->hasOneNotFrom($this->OpenBracketOrNotIndex)
                        ) {
                            $token->WhitespaceAfter |= WhitespaceType::LINE;
                        }
                    };
            }
        }

        $this->CommaIndex = TokenType::getIndex(\T_COMMA);
        $this->SemicolonIndex = TokenType::getIndex(\T_SEMICOLON);
        $this->OpenBracketOrNotIndex = TokenType::getIndex(
            \T_OPEN_BRACE,
            \T_OPEN_BRACKET,
            \T_OPEN_PARENTHESIS,
            \T_LOGICAL_NOT,
            \T_NOT,
        );
        $this->BooleanHasLineBreakClosure = $hasLineBreak;
        $this->ApplyBooleanLineBreakClosure = $applyLineBreak;
        $this->EmptyBooleansByType = Arr::toIndex($booleanTypes, null);
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Propagate newlines adjacent to boolean operators to others of
            // equal or lower precedence in the same statement
            if ($this->TypeIndex->OperatorBooleanExceptNot[$token->id]) {
                $tokenId = self::BOOLEAN_MAP[$token->id] ?? $token->id;

                // Ignore statements already processed and tokens with no
                // adjacent newline
                if (
                    ($this->Seen[$token->Statement->Index] ?? false)
                    || !($this->BooleanHasLineBreakClosure[$tokenId])($token)
                ) {
                    continue;
                }

                // Get the statement's boolean operators and find the
                // highest-precedence operator with an adjacent newline
                /** @var array<int,Token[]|null> */
                $byType = $this->EmptyBooleansByType;
                $minPrecedence = self::BOOLEAN_PRECEDENCE[$tokenId];
                foreach ($token->Statement->collectSiblings($token->EndStatement) as $t) {
                    if (!$this->TypeIndex->OperatorBooleanExceptNot[$t->id]) {
                        continue;
                    }
                    $id = self::BOOLEAN_MAP[$t->id] ?? $t->id;
                    $byType[$id][] = $t;
                    if ($t === $token || !($t->hasNewlineAfter() || $t->hasNewlineBefore())) {
                        continue;
                    }
                    $minPrecedence = min($minPrecedence, self::BOOLEAN_PRECEDENCE[$id]);
                }

                foreach ($byType as $type => $tokens) {
                    if (!$tokens || self::BOOLEAN_PRECEDENCE[$type] < $minPrecedence) {
                        continue;
                    }
                    foreach ($tokens as $t) {
                        ($this->ApplyBooleanLineBreakClosure[$type])($t);
                    }
                }

                $this->Seen[$token->Statement->Index] = true;
                continue;
            }

            if ($token->id === \T_FOR) {
                $children = $token->NextCode->children();
                $commas = $children->getAnyFrom($this->CommaIndex);
                $semicolons = $children->getAnyFrom($this->SemicolonIndex);
                $semi1 = $semicolons->first();
                $semi2 = $semicolons->last();
                $expr1 = $token->NextCode->Next->collectSiblings($semi1);
                $expr2 = $semi1->Next->collectSiblings($semi2);
                $expr3 = $semi2->Next->collectSiblings($token->NextCode->ClosedBy->Prev);

                // If an expression in a `for` loop breaks over multiple lines,
                // add a newline after each comma-delimited expression and a
                // blank line between each semicolon-delimited expression
                if ($expr1->hasNewlineBetweenTokens()
                        || $expr2->hasNewlineBetweenTokens()
                        || $expr3->hasNewlineBetweenTokens()) {
                    $commas->addWhitespaceAfter(WhitespaceType::LINE);
                    $semicolons->addWhitespaceAfter(WhitespaceType::BLANK);
                } elseif ($semicolons->tokenHasNewlineAfter()) {
                    // If the second or third expression in a `for` loop is at
                    // the start of a line, add a newline before the other
                    $semicolons->addWhitespaceAfter(WhitespaceType::LINE);
                }

                // Suppress whitespace in empty `for` loop expressions
                foreach ([[$expr1, 1], [$expr2, 1], [$expr3, 0]] as [$expr, $emptyCount]) {
                    if ($expr->count() === $emptyCount) {
                        if ($emptyCount) {
                            $expr->maskWhitespaceBefore(WhitespaceType::NONE);
                        } else {
                            $semi2->WhitespaceMaskNext = WhitespaceType::NONE;
                            $semi2->Next->WhitespaceMaskPrev = WhitespaceType::NONE;
                        }
                    }
                }

                continue;
            }

            // Add a newline before an open brace that is part of a top-level
            // declaration or an anonymous class declared over multiple lines
            if ($token->id === \T_OPEN_BRACE) {
                if (!$token->isStructuralBrace(true)
                        || ($token->Next->id === \T_CLOSE_BRACE && !$token->hasNewlineAfter())) {
                    continue;
                }
                $parts = $token->Expression->declarationParts();
                if (!$this->Formatter->OneTrueBraceStyle
                        && $parts->hasOneOf(...TokenType::DECLARATION)
                        && ($last = $parts->last())->id !== \T_DECLARE
                        && $last->skipPrevSiblingsOf(...TokenType::AMPERSAND)->id !== \T_FUNCTION) {
                    $start = $parts->first();
                    if ($start->id !== \T_USE
                        && ((!($prevCode = $start->PrevCode)
                                || $prevCode->id === \T_SEMICOLON
                                || $prevCode->id === \T_OPEN_BRACE
                                || $prevCode->id === \T_CLOSE_BRACE
                                || $prevCode->id === \T_CLOSE_TAG)
                            || ($start->id === \T_NEW && $parts->hasNewlineBetweenTokens()))) {
                        $token->WhitespaceBefore |= WhitespaceType::LINE;
                    }
                }
                continue;
            }

            // If one ternary operator is at the start of a line, add a newline
            // before the other
            if ($token->id === \T_QUESTION) {
                if (
                    !($token->Flags & TokenFlag::TERNARY_OPERATOR)
                    || $token->OtherTernaryOperator === $token->Next
                ) {
                    continue;
                }

                $op1Newline = $token->hasNewlineBefore();
                $op2Newline = $token->OtherTernaryOperator->hasNewlineBefore();
                if ($op1Newline && !$op2Newline) {
                    $token->OtherTernaryOperator->WhitespaceBefore |= WhitespaceType::LINE;
                } elseif (!$op1Newline && $op2Newline) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                }
                continue;
            }

            // If an object operator (`->` or `?->`) is at the start of a line,
            // add a newline before other object operators in the same chain
            if ($token !== $token->ChainOpenedBy) {
                continue;
            }

            $chain = $token->withNextSiblingsWhile(false, ...TokenType::CHAIN_PART)
                           ->getAnyFrom($this->TypeIndex->Chain);

            if ($chain->count() < 2
                    || !$chain->find(fn(Token $t) => $t->hasNewlineBefore())) {
                continue;
            }

            // Leave the first object operator alone if chain alignment is
            // enabled and strict PSR-12 compliance isn't
            if (($this->Formatter->Enabled[AlignChains::class] ?? null)
                    && !$this->Formatter->Psr12) {
                $chain->shift();
            }

            $chain->addWhitespaceBefore(WhitespaceType::LINE);
        }
    }
}
