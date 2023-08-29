<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply sensible vertical whitespace
 *
 * Specifically:
 *
 * - If one expression in a `for` loop is at the start of a line, add a newline
 *   before the others
 * - If one ternary operator is at the start of a line, add a newline before the
 *   other
 * - If an object operator (`->` or `?->`) is at the start of a line, add a
 *   newline before other object operators in the same chain
 */
final class OperatorLineBreaks implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 98;
    }

    public function getTokenTypes(): array
    {
        return [
            T_FOR,
            T_QUESTION,
            ...TokenType::CHAIN,
        ];
    }

    public function processToken(Token $token): void
    {
        // If one expression in a `for` loop is at the start of a line, add a
        // newline before the others
        if ($token->id === T_FOR) {
            $terminators =
                $token->_nextCode
                      ->innerSiblings()
                      ->filter(fn(Token $t) => $t->id === T_SEMICOLON);

            if ($terminators->tokenHasNewlineAfter()) {
                $terminators->addWhitespaceAfter(WhitespaceType::LINE);
            }
            return;
        }

        // If one ternary operator is at the start of a line, add a newline
        // before the other
        if ($token->id === T_QUESTION) {
            if (!$token->IsTernaryOperator ||
                    $token->TernaryOperator2 === $token->_next) {
                return;
            }

            $op1Newline = $token->hasNewlineBefore();
            $op2Newline = $token->TernaryOperator2->hasNewlineBefore();
            if ($op1Newline && !$op2Newline) {
                $token->TernaryOperator2->WhitespaceBefore |= WhitespaceType::LINE;
            } elseif (!$op1Newline && $op2Newline) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
            }
            return;
        }

        // If an object operator (`->` or `?->`) is at the start of a line, add
        // a newline before other object operators in the same chain
        if ($token !== $token->ChainOpenedBy) {
            return;
        }

        $chain = $token->withNextSiblingsWhile(...TokenType::CHAIN_PART)
                       ->filter(fn(Token $t) => $this->TypeIndex->Chain[$t->id]);

        if ($chain->count() < 2 ||
                !$chain->find(fn(Token $t) => $t->hasNewlineBefore())) {
            return;
        }

        // Leave the first object operator alone if chain alignment is enabled
        // or if there are no structures that would end up like this:
        //
        //     $foxtrot->foo(
        //         //
        //     )
        //         ->baz();
        if (($this->Formatter->EnabledRules[AlignChains::class] ?? false) ||
                !$chain->find(
                    fn(Token $t) =>
                        $this->TypeIndex->CloseBracket[$t->_prevCode->id] &&
                            $t->_prevCode->hasNewlineBefore()
                )) {
            $chain->shift();
        }

        $chain->addWhitespaceBefore(WhitespaceType::LINE);
    }
}
