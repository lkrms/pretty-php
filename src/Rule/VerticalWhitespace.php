<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;
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

    private const TOKEN_MAP = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => \T_AND,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => \T_AND,
        \T_AND => \T_AND,
        \T_BOOLEAN_AND => \T_BOOLEAN_AND,
        \T_BOOLEAN_OR => \T_BOOLEAN_OR,
        \T_LOGICAL_AND => \T_LOGICAL_AND,
        \T_LOGICAL_OR => \T_LOGICAL_OR,
        \T_LOGICAL_XOR => \T_LOGICAL_XOR,
        \T_OR => \T_OR,
        \T_XOR => \T_XOR,
    ];

    private const PRECEDENCE_MAP = [
        \T_AND => 0,
        \T_XOR => 1,
        \T_OR => 2,
        \T_BOOLEAN_AND => 3,
        \T_BOOLEAN_OR => 4,
        \T_LOGICAL_AND => 5,
        \T_LOGICAL_XOR => 6,
        \T_LOGICAL_OR => 7,
    ];

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

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return TokenTypeIndex::merge(
            TokenTypeIndex::get(
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

        $hasNewlineBefore = fn(Token $t): bool => $t->hasNewlineBefore();
        $hasNewlineAfter = fn(Token $t): bool => $t->hasNewlineBeforeNextCode();
        $applyNewlineBefore = function (Token $t): void {
            $sol = $t->startOfLine();
            // Don't add a newline after a standalone close bracket, e.g. `) &&`
            // where `)` is at the start of a line
            if ($sol === $t || (
                $t->Prev
                && $sol->collect($t->Prev)->hasOneNotFrom($this->Idx->CloseBracket)
            )) {
                $t->WhitespaceBefore |= WhitespaceType::LINE;
            }
        };
        $applyNewlineAfter = function (Token $t): void {
            $eol = $t->endOfLine();
            // Don't add a newline before a standalone open bracket, e.g. `&& (`
            // where `(` is at the end of a line
            if ($eol === $t || (
                $t->Next
                && $t->Next->collect($eol)->hasOneNotFrom($this->Idx->OpenBracketOrNot)
            )) {
                $t->WhitespaceAfter |= WhitespaceType::LINE;
            }
        };

        foreach (array_keys(self::PRECEDENCE_MAP) as $id) {
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
                $id = self::TOKEN_MAP[$token->id];

                assert($token->Statement !== null);

                // Ignore statements already processed and tokens with no
                // adjacent newline
                if (
                    isset($this->Seen[$token->Statement->Index])
                    || !($this->HasNewline[$id])($token)
                ) {
                    continue;
                }

                $this->Seen[$token->Statement->Index] = true;

                // Get the statement's boolean operators and find the
                // highest-precedence operator with an adjacent newline
                /** @var array<int,Token[]> */
                $byType = [];
                $minPrecedence = self::PRECEDENCE_MAP[$id];

                foreach ($token->Statement->collectSiblings($token->EndStatement) as $t) {
                    if (!$this->Idx->OperatorBooleanExceptNot[$t->id]) {
                        continue;
                    }
                    $id = self::TOKEN_MAP[$t->id];
                    $byType[$id][] = $t;
                    if ($t === $token || !($this->HasNewline[$id])($t)) {
                        continue;
                    }
                    $minPrecedence = min($minPrecedence, self::PRECEDENCE_MAP[$id]);
                }

                foreach ($byType as $id => $tokens) {
                    if (self::PRECEDENCE_MAP[$id] >= $minPrecedence) {
                        foreach ($tokens as $t) {
                            ($this->ApplyNewline[$id])($t);
                        }
                    }
                }

                continue;
            }

            if ($token->id === \T_FOR) {
                assert(
                    $token->NextCode
                    && $token->NextCode->Next
                    && $token->NextCode->ClosedBy
                    && $token->NextCode->ClosedBy->Prev
                );

                $children = $token->NextCode->children();
                $commas = $children->getAnyOf(\T_COMMA);
                $semicolons = $children->getAnyOf(\T_SEMICOLON);
                $semi1 = $semicolons->first();
                $semi2 = $semicolons->last();

                assert($semi1 && $semi1->Next && $semi2 && $semi2->Next);

                $expr1 = $token->NextCode->Next->collectSiblings($semi1);
                $expr2 = $semi1->Next->collectSiblings($semi2);
                $expr3 = $semi2->Next->collectSiblings($token->NextCode->ClosedBy->Prev);

                // If an expression in a `for` loop breaks over multiple lines,
                // add a newline after each comma-delimited expression and a
                // blank line between each semicolon-delimited expression
                if (
                    $expr1->hasNewlineBetweenTokens()
                    || $expr2->hasNewlineBetweenTokens()
                    || $expr3->hasNewlineBetweenTokens()
                ) {
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

                $parts = $token->skipPrevSiblingsToDeclarationStart()->declarationParts();
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
                    || $last->skipPrevSiblingsFrom($this->Idx->Ampersand)->id === \T_FUNCTION
                    // Exclude anonymous classes declared on one line
                    || ($start->id === \T_NEW && !$parts->hasNewlineBetweenTokens())
                ) {
                    continue;
                }

                $token->WhitespaceBefore |= WhitespaceType::LINE;

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
                    $other->WhitespaceBefore |= WhitespaceType::LINE;
                } elseif (!$op1Newline && $op2Newline) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                }

                continue;
            }

            // if ($this->Idx->Chain[$token->id]) {

            // If an object operator (`->` or `?->`) is at the start of a line,
            // add a newline before other object operators in the same chain
            if ($token !== $token->Data[TokenData::CHAIN_OPENED_BY]) {
                continue;
            }

            $chain = $token->withNextSiblingsWhile($this->Idx->ChainPart)
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
                $chain->shift();
            }

            $chain->addWhitespaceBefore(WhitespaceType::LINE);

            // }
        }
    }
}
