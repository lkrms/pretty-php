<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Apply whitespace to statement terminators
 *
 * @api
 */
final class StatementSpacing implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 80,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_COLON => true,
            \T_SEMICOLON => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * In `for` loop expressions, a space is added after semicolons where the
     * next expression is not empty.
     *
     * Whitespace is suppressed before, and newlines are added after, semicolons
     * in other contexts and colons in alternative syntax constructs, `switch`
     * cases and labels.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $collapse = true;
            if ($token->id === \T_COLON) {
                if (
                    !$token->CloseBracket
                    && $token->EndStatement !== $token
                ) {
                    continue;
                }
            } else {
                if (
                    ($parent = $token->Parent)
                    && $parent->id === \T_OPEN_PARENTHESIS
                    && ($prev = $parent->PrevCode)
                    && $prev->id === \T_FOR
                ) {
                    if (
                        ($next = $token->NextSibling)
                        && $next->id !== \T_SEMICOLON
                    ) {
                        $token->Whitespace |= Space::SPACE_AFTER;
                    }
                    continue;
                }

                /** @var Token */
                $statement = $token->Statement;

                // Don't make any changes after __halt_compiler()
                if ($statement->id === \T_HALT_COMPILER) {
                    continue;
                }

                // Don't collapse vertical whitespace between open braces and
                // empty statements
                if ($statement === $token) {
                    if ($this->Formatter->DetectProblems) {
                        $this->Formatter->registerProblem(
                            'Empty statement',
                            $token,
                        );
                    }
                    if (
                        ($prev = $token->Prev)
                        && (
                            $this->Idx->OpenBracket[$prev->id]
                            || ($prev->id === \T_COLON && $prev->CloseBracket)
                        )
                    ) {
                        $collapse = false;
                    }
                }
            }

            $token->Whitespace |= Space::LINE_AFTER
                | Space::SPACE_AFTER
                | ($collapse
                    ? Space::NONE_BEFORE
                    : Space::NO_SPACE_BEFORE);
        }
    }
}
