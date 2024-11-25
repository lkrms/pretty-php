<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Token;

/**
 * @api
 *
 * @phpstan-require-implements Rule
 */
trait RuleTrait
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function beforeRender(array $tokens): void {}

    /**
     * Suppress vertical whitespace between the given tokens if they were on the
     * same line
     *
     * @param bool $force If `true`, suppress vertical whitespace even if the
     * tokens were on different lines.
     * @param bool $checkStatement If `true`, only suppress vertical whitespace
     * if the tokens belong to the same statement.
     * @return bool `true` if vertical whitespace is suppressed, otherwise
     * `false`.
     */
    private function preserveOneLine(
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

        $start->collect($end)
              ->applyInnerWhitespace(Space::CRITICAL_NO_BLANK | Space::CRITICAL_NO_LINE);

        return true;
    }

    /**
     * Copy an open bracket's inner whitespace to its close bracket
     */
    private function mirrorBracket(
        Token $open,
        ?bool $hasNewlineBeforeNextCode = null
    ): void {
        /** @var Token */
        $close = $open->ClosedBy;

        if ($hasNewlineBeforeNextCode === null) {
            $hasNewlineBeforeNextCode = $open->hasNewlineBeforeNextCode();
        }
        if (!$hasNewlineBeforeNextCode) {
            $close->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
            return;
        }

        $close->Whitespace |= Space::LINE_BEFORE;
        if (!$close->hasNewlineBefore()) {
            $close->removeWhitespace(Space::NO_LINE_BEFORE);
        }
    }
}
