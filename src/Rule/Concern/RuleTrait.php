<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * @phpstan-require-implements Rule
 */
trait RuleTrait
{
    use ExtensionTrait;

    /**
     * Suppress vertical whitespace between the given tokens if they were on the
     * same line
     *
     * If `$force` is `true`, vertical whitespace is suppressed even if the
     * tokens were on different lines. If `$oneStatement` is `true`, vertical
     * whitespace is only suppressed if the tokens belong to the same statement.
     *
     * Returns `true` if vertical whitespace is suppressed, otherwise `false`.
     */
    protected function preserveOneLine(Token $start, Token $end, bool $force = false, bool $oneStatement = false): bool
    {
        if (!$force && $start->line !== $end->line) {
            return false;
        }

        if ($oneStatement) {
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
              ->maskInnerWhitespace(~WhitespaceType::BLANK & ~WhitespaceType::LINE, true);

        return true;
    }

    /**
     * Copy an open bracket's inner whitespace to its closing bracket
     */
    protected function mirrorBracket(Token $openBracket, ?bool $hasNewlineBeforeNextCode = null): void
    {
        assert($openBracket->ClosedBy !== null);
        if ($hasNewlineBeforeNextCode === false || (
            $hasNewlineBeforeNextCode === null
            && !$openBracket->hasNewlineBeforeNextCode()
        )) {
            $openBracket->ClosedBy->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            return;
        }

        $openBracket->ClosedBy->WhitespaceBefore |= WhitespaceType::LINE;
        if (!$openBracket->ClosedBy->hasNewlineBefore()) {
            assert($openBracket->ClosedBy->Prev !== null);
            $openBracket->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $openBracket->ClosedBy->Prev->WhitespaceMaskNext |= WhitespaceType::LINE;
        }
    }
}
