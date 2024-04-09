<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Token\Token;

trait RuleTrait
{
    use ExtensionTrait;

    protected function preserveOneLine(Token $start, Token $end, bool $force = false, bool $oneStatement = false): bool
    {
        if (!$force && $start->line !== $end->line) {
            return false;
        }

        if ($oneStatement) {
            $from = $start->IsCode ? $start : $start->NextCode;
            $to = $end->IsCode ? $end : $end->PrevCode;
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

    protected function mirrorBracket(Token $openBracket, ?bool $hasNewlineBeforeNextCode = null): void
    {
        assert($openBracket->ClosedBy !== null);
        if (
            $hasNewlineBeforeNextCode === false
            || !$openBracket->hasNewlineBeforeNextCode()
        ) {
            $openBracket->ClosedBy->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            return;
        }

        $openBracket->ClosedBy->WhitespaceBefore |= WhitespaceType::LINE;
        if (!$openBracket->ClosedBy->hasNewlineBefore()) {
            $openBracket->ClosedBy->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $openBracket->ClosedBy->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
        }
    }
}
