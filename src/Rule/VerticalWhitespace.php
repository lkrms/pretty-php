<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply sensible vertical spacing
 *
 * - If an expression in a `for` loop breaks over multiple lines, add a newline
 *   after each comma-delimited expression and a blank line between each
 *   semicolon-delimited expression
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

    /**
     * @var array<int,bool>
     */
    private $CommaIndex;

    /**
     * @var array<int,bool>
     */
    private $SemicolonIndex;

    public function prepare()
    {
        $this->CommaIndex = TokenType::getIndex(T_COMMA);
        $this->SemicolonIndex = TokenType::getIndex(T_SEMICOLON);
        return $this;
    }

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 98;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_FOR,
            T_QUESTION,
            ...TokenType::CHAIN,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === T_FOR) {
                $children = $token->_nextCode->children();
                $commas = $children->getAnyFrom($this->CommaIndex);
                $semicolons = $children->getAnyFrom($this->SemicolonIndex);
                $semi1 = $semicolons->first();
                $semi2 = $semicolons->last();
                $expr1 = $token->_nextCode->_next->collectSiblings($semi1);
                $expr2 = $semi1->_next->collectSiblings($semi2);
                $expr3 = $semi2->_next->collectSiblings($token->_nextCode->ClosedBy->_prev);

                // If an expression in a `for` loop breaks over multiple lines,
                // add a newline after each comma-delimited expression and a
                // blank line between each semicolon-delimited expression
                if ($expr1->hasNewlineBetweenTokens() ||
                        $expr2->hasNewlineBetweenTokens() ||
                        $expr3->hasNewlineBetweenTokens()) {
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
                            $semi2->_next->WhitespaceMaskPrev = WhitespaceType::NONE;
                        }
                    }
                }

                continue;
            }

            // If one ternary operator is at the start of a line, add a newline
            // before the other
            if ($token->id === T_QUESTION) {
                if (!$token->IsTernaryOperator ||
                        $token->TernaryOperator2 === $token->_next) {
                    continue;
                }

                $op1Newline = $token->hasNewlineBefore();
                $op2Newline = $token->TernaryOperator2->hasNewlineBefore();
                if ($op1Newline && !$op2Newline) {
                    $token->TernaryOperator2->WhitespaceBefore |= WhitespaceType::LINE;
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

            if ($chain->count() < 2 ||
                    !$chain->find(fn(Token $t) => $t->hasNewlineBefore())) {
                continue;
            }

            // Leave the first object operator alone if chain alignment is
            // enabled and strict PSR-12 compliance isn't
            if (($this->Formatter->EnabledRules[AlignChains::class] ?? null) &&
                    !$this->Formatter->Psr12Compliance) {
                $chain->shift();
            }

            $chain->addWhitespaceBefore(WhitespaceType::LINE);
        }
    }
}
