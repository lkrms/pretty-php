<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;

/**
 * If a control structure expression breaks over multiple lines, add newlines
 * before and after it
 *
 * Necessary for PSR-12 compliance.
 *
 * @api
 */
final class StrictExpressions implements MultiTokenRule
{
    use MultiTokenRuleTrait;

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
            T_IF,
            T_ELSEIF,
            T_SWITCH,
            T_WHILE,
            T_FOR,
            T_FOREACH,
        ];
    }

    public function getRequiresSortedTokens(): bool
    {
        return false;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $first = $token->_nextCode;
            if ($first->hasNewlineAfter()) {
                continue;
            }
            $last = $first->ClosedBy;
            if ($first->collect($last)->hasNewline()) {
                $first->WhitespaceAfter |= WhitespaceType::LINE;
                $first->WhitespaceMaskNext |= WhitespaceType::LINE;
                $first->_nextCode->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $last->WhitespaceBefore |= WhitespaceType::LINE;
                $last->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $last->_prevCode->WhitespaceMaskNext |= WhitespaceType::LINE;
            }
        }
    }
}
