<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Normalise multi-line lists
 *
 * Specifically:
 * - If an interface list (`extends` or `implements`, depending on context)
 *   breaks over multiple lines and neither {@see StrictLists} nor
 *   {@see AlignLists} are enabled, add a newline before the first interface.
 * - If a parameter list breaks over multiple lines and contains at least one
 *   `T_ATTRIBUTE`, place every attribute and annotated parameter on its own
 *   line, and add blank lines before and after annotated parameters to improve
 *   readability.
 *
 */
final class ListSpacing implements ListRule
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
                [StrictLists::class => true, AlignLists::class => true],
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
                !($owner->prevCode()->id === T_FN || $owner->isDeclaration(T_FUNCTION)) ||
                !($items->hasOneOf(T_ATTRIBUTE, T_ATTRIBUTE_COMMENT) && $items->hasNewlineBetweenTokens())) {
            return;
        }

        $blankBeforeNext = false;
        foreach ($items as $token) {
            $blankBeforeApplied = $blankBeforeNext;
            if ($blankBeforeNext) {
                $token->applyBlankLineBefore(true);
                $blankBeforeNext = false;
            }
            $current = $token;
            while ($current->id === T_ATTRIBUTE ||
                    $current->id === T_ATTRIBUTE_COMMENT) {
                $current->WhitespaceBefore |= WhitespaceType::LINE;
                if ($current->id === T_ATTRIBUTE) {
                    $current->ClosedBy->WhitespaceAfter |= WhitespaceType::LINE;
                } else {
                    $current->WhitespaceAfter |= WhitespaceType::LINE;
                }
                $current->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $current->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
                $current = $current->_nextSibling;
            }
            if ($current === $token) {
                $prev = $token;
                continue;
            }
            if (!$blankBeforeApplied && ($prev ?? null)) {
                $token->applyBlankLineBefore(true);
            }
            $blankBeforeNext = true;
            $prev = $token;
        }
    }
}
