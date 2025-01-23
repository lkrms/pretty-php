<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

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
            self::PROCESS_TOKENS => 120,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
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
     * For semicolons in other contexts, and colons in alternative syntax
     * constructs, `switch` cases and labels, leading whitespace is suppressed
     * and trailing newlines are added.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $before = Space::NONE_BEFORE;
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

                // Don't collapse vertical whitespace between open brackets/tags
                // and empty statements
                if ($statement === $token) {
                    if ($this->Formatter->DetectProblems) {
                        $this->Formatter->registerProblem(
                            'Empty statement',
                            $token,
                        );
                    }
                    /** @var Token */
                    $prev = $token->Prev;
                    if (
                        $this->Idx->OpenBracket[$prev->id]
                        || ($prev->id === \T_COLON && $prev->CloseBracket)
                    ) {
                        $before = Space::NO_SPACE_BEFORE;
                    } elseif ($prev->id === \T_OPEN_TAG) {
                        $before = 0;
                    }
                }
            }

            $token->Whitespace |= $before
                | Space::LINE_AFTER
                | Space::SPACE_AFTER;
        }
    }
}
