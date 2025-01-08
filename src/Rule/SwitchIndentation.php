<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Apply switch case list indentation
 *
 * @api
 */
final class SwitchIndentation implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 302,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return [
            \T_SWITCH => true,
            \T_CASE => true,
            \T_DEFAULT => true,
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
     * In switch case lists:
     *
     * - The `PreIndent` of every token is incremented.
     * - The `Deindent` of tokens between `case` or `default` and their
     *   respective delimiters is incremented.
     * - Newlines are added before `case` and `default` and after their
     *   respective delimiters.
     * - Blank lines are suppressed after `case` and `default` delimiters.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_SWITCH) {
                /** @var Token */
                $next = $token->NextSibling;
                /** @var Token */
                $next = $next->NextSibling;
                foreach ($next->inner() as $t) {
                    $t->PreIndent++;
                }
            } elseif ($token->inSwitch()) {
                /** @var Token */
                $separator = $token->EndStatement;
                $token->Whitespace |= Space::LINE_BEFORE;
                $separator->Whitespace |= Space::NO_BLANK_AFTER | Space::LINE_AFTER | Space::SPACE_AFTER;
                foreach ($token->collect($separator) as $t) {
                    $t->Deindent++;
                }
            }
        }
    }
}
