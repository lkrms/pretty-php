<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * Apply whitespace to statement terminators
 */
final class StatementSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 80,
        ][$method] ?? null;
    }

    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_COLON => true,
            \T_SEMICOLON => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $collapse = true;
            if ($token->id === \T_COLON) {
                if (
                    !$token->CloseBracket
                    && !$token->isColonStatementDelimiter()
                ) {
                    continue;
                }
            } else {
                // Add space after `for` loop expression delimiters where the
                // next expression is non-empty
                if (
                    ($parent = $token->Parent)
                    && $parent->id === \T_OPEN_PARENTHESIS
                    && ($prev = $parent->PrevCode)
                    && $prev->id === \T_FOR
                ) {
                    if (
                        $token->NextSibling
                        && $token->NextSibling->id !== \T_SEMICOLON
                    ) {
                        $token->applyWhitespace(Space::SPACE_AFTER);
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
                    if (($prev = $token->Prev) && (
                        $this->Idx->OpenBracket[$prev->id]
                        || ($prev->id === \T_COLON && $prev->CloseBracket)
                    )) {
                        $collapse = false;
                    }
                }
            }

            $token->Whitespace |= ($collapse
                    ? Space::NONE_BEFORE
                    : Space::NO_SPACE_BEFORE)
                | Space::LINE_AFTER
                | Space::SPACE_AFTER;
        }
    }
}
