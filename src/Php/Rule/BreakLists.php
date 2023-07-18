<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

/**
 * Normalise multi-line lists
 *
 * Specifically:
 * - If an interface list (`extends` or `implements`, depending on context)
 *   breaks over multiple lines and neither {@see NoMixedLists} nor
 *   {@see AlignLists} are enabled, add a newline before the first interface.
 * - If one or more parameters in an argument list break over multiple lines to
 *   accommodate a T_ATTRIBUTE, place every parameter on its own line, and add
 *   blank lines before and after annotated parameters to improve readability.
 *
 */
final class BreakLists implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 98;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        // If `$owner` has no `ClosedBy`, this is an interface list
        if (!$owner->ClosedBy) {
            if (!array_intersect_key(
                [NoMixedLists::class => true, AlignLists::class => true],
                $this->Formatter->EnabledRules
            ) && $items->find(
                fn(Token $token) => $token->hasNewlineBefore()
            )) {
                $first = $items->first();
                $first->WhitespaceBefore |= WhitespaceType::LINE;
                $first->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $first->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
            }
            return;
        }

        if ($owner->id !== T_OPEN_PARENTHESIS ||
                !($owner->prevCode()->id === T_FN ||
                    $owner->isDeclaration(T_FUNCTION)) ||
                !$items->find(fn(Token $token) => $this->hasAttributeOnOwnLine($token))) {
            return;
        }

        $items->forEach(
            function (Token $token, ?Token $next, ?Token $prev) {
                if (!$this->hasAttributeOnOwnLine($token)) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                    $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
                    $token->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
                    return;
                }
                if ($prev) {
                    $token->WhitespaceBefore |= WhitespaceType::BLANK;
                    $token->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                    $token->_prev->WhitespaceMaskNext |= WhitespaceType::BLANK;
                }
                if ($next) {
                    $next->WhitespaceBefore |= WhitespaceType::BLANK;
                    $next->WhitespaceMaskPrev |= WhitespaceType::BLANK;
                    $next->_prev->WhitespaceMaskNext |= WhitespaceType::BLANK;
                }
            }
        );
    }

    private function hasAttributeOnOwnLine(Token $token): bool
    {
        return $token->is([T_ATTRIBUTE, T_ATTRIBUTE_COMMENT]) &&
            $token->hasNewlineBefore() &&
            ($token->ClosedBy ?: $token)->hasNewlineAfter();
    }
}
