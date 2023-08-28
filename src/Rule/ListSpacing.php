<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;

/**
 * Apply whitespace to lists
 *
 * Arrays and argument lists with trailing ("magic") commas are split into one
 * item per line.
 *
 * If interface lists (`extends` or `implements`, depending on context) break
 * over multiple lines and neither {@see StrictLists} nor {@see AlignLists} are
 * enabled, a newline is added before the first interface.
 *
 * If parameter lists contain one or more attributes with a leading or trailing
 * newline, every attribute and parameter is placed on its own line, and blank
 * lines are added before and after annotated parameters to improve readability.
 *
 * @api
 */
final class ListSpacing implements ListRule
{
    use ListRuleTrait {
        setFormatter as private _setFormatter;
    }

    private bool $ListRuleIsEnabled;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_LIST:
                return 98;

            default:
                return null;
        }
    }

    public function setFormatter(Formatter $formatter): void
    {
        $this->_setFormatter($formatter);
        $this->ListRuleIsEnabled =
            ($formatter->EnabledRules[StrictLists::class] ?? null) ||
                ($formatter->EnabledRules[AlignLists::class] ?? null);
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        // If `$owner` has no `ClosedBy`, this is an interface list
        if (!$owner->ClosedBy) {
            if (!$this->ListRuleIsEnabled &&
                    $items->tokenHasNewlineBefore()) {
                $first = $items->first();
                $first->WhitespaceBefore |= WhitespaceType::LINE;
                $first->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $first->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
            }
            return;
        }

        // If the list has a "magic comma", add a newline before each item and
        // another after the last item
        if ($owner->ClosedBy->_prevCode->id === T_COMMA) {
            $items->push($owner->ClosedBy)
                  ->addWhitespaceBefore(WhitespaceType::LINE, true);
        }

        if ($owner->id !== T_OPEN_PARENTHESIS ||
                !$owner->isParameterList()) {
            return;
        }

        $hasAttributeWithNewline = false;
        foreach ($items as $item) {
            $current = $item;
            while ($current->id === T_ATTRIBUTE ||
                    $current->id === T_ATTRIBUTE_COMMENT) {
                if ($current->hasNewlineBefore() ||
                    ($current->id === T_ATTRIBUTE
                        ? $current->ClosedBy
                        : $current)->hasNewlineAfter()) {
                    $hasAttributeWithNewline = true;
                    break 2;
                }
                if (!($current = $current->_nextSibling)) {
                    break;
                }
            }
        }
        if (!$hasAttributeWithNewline) {
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
            do {
                $current->WhitespaceBefore |= WhitespaceType::LINE;
                $current->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $current->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
                if ($current->id === T_ATTRIBUTE) {
                    $current->ClosedBy->WhitespaceAfter |= WhitespaceType::LINE;
                } elseif ($current->id === T_ATTRIBUTE_COMMENT) {
                    $current->WhitespaceAfter |= WhitespaceType::LINE;
                }
            } while (($current->id === T_ATTRIBUTE ||
                    $current->id === T_ATTRIBUTE_COMMENT) &&
                ($current = $current->_nextSibling));

            // Continue if $token is a parameter with no attributes
            if ($current === $token) {
                $prev = $token;
                continue;
            }

            // Otherwise, add a blank line before $token and another before the
            // next parameter
            if (!$blankBeforeApplied && ($prev ?? null)) {
                $token->applyBlankLineBefore(true);
            }
            $blankBeforeNext = true;

            $prev = $token;
        }
    }
}
