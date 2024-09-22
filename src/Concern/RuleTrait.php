<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * @api
 *
 * @phpstan-require-implements Rule
 */
trait RuleTrait
{
    use ExtensionTrait;

    /**
     * Suppress vertical whitespace between the given tokens if they were on the
     * same line
     *
     * @param bool $force If `true`, suppress vertical whitespace even if the
     * tokens were on different lines.
     * @param bool $checkStatement If `true`, only suppress vertical
     * whitespace if the tokens belong to the same statement.
     * @return bool `true` if vertical whitespace is suppressed, otherwise
     * `false`.
     */
    protected function preserveOneLine(
        Token $start,
        Token $end,
        bool $force = false,
        bool $checkStatement = false
    ): bool {
        if (!$force && $start->line !== $end->line) {
            return false;
        }

        if ($checkStatement) {
            $from = $start->Flags & TokenFlag::CODE ? $start : $start->NextCode;
            $to = $end->Flags & TokenFlag::CODE ? $end : $end->PrevCode;
            if (
                $from
                && $to
                && $from->Index <= $to->Index
                && $from->Statement !== $to->Statement
            ) {
                return false;
            }
        }

        $mask = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
        $start->collect($end)
              ->maskInnerWhitespace($mask, true);

        return true;
    }

    /**
     * Copy an open bracket's inner whitespace to its close bracket
     */
    protected function mirrorBracket(
        Token $open,
        ?bool $hasNewlineBeforeNextCode = null
    ): void {
        /** @var Token */
        $close = $open->ClosedBy;

        if ($hasNewlineBeforeNextCode === null) {
            $hasNewlineBeforeNextCode = $open->hasNewlineBeforeNextCode();
        }
        if (!$hasNewlineBeforeNextCode) {
            $mask = ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $close->WhitespaceMaskPrev &= $mask;
            return;
        }

        $close->WhitespaceBefore |= WhitespaceType::LINE;
        if (!$close->hasNewlineBefore()) {
            /** @var Token */
            $prev = $close->Prev;
            $close->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $prev->WhitespaceMaskNext |= WhitespaceType::LINE;
        }
    }
}
