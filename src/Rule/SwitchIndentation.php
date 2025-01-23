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
            /** @var Token */
            $next = $token->NextSibling;
            /** @var Token */
            $open = $next->NextSibling;
            /** @var Token */
            $close = $open->CloseBracket;
            /** @var Token */
            $first = $open->NextCode;
            if ($first === $close) {
                continue;
            }
            if (!$this->Idx->CaseOrDefault[$first->id]) {
                $first = $first->nextSiblingFrom($this->Idx->CaseOrDefault);
                if ($first->id === \T_NULL) {
                    continue;
                }
            }
            /** @var Token */
            $last = $close->Prev;
            foreach ($first->collect($last) as $t) {
                $t->PreIndent++;
            }
            $cases = $first->withNextSiblings()
                           ->getAnyFrom($this->Idx->CaseOrDefault);
            foreach ($cases as $case) {
                /** @var Token */
                $end = $case->EndStatement;
                $case->Whitespace |= Space::LINE_BEFORE;
                $end->Whitespace |= Space::NO_BLANK_AFTER | Space::LINE_AFTER | Space::SPACE_AFTER;
                foreach ($case->collect($end) as $t) {
                    $t->Deindent++;
                }
            }
        }
    }
}
