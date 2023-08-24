<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Token\Token;

trait RuleTrait
{
    use ExtensionTrait;

    protected function preserveOneLine(Token $start, Token $end, bool $force = false): bool
    {
        if (!$force && $start->line !== $end->line) {
            return false;
        }

        $start->collect($end)
              ->maskInnerWhitespace(~WhitespaceType::BLANK & ~WhitespaceType::LINE, true);

        return true;
    }

    protected function mirrorBracket(Token $openBracket, ?bool $hasNewlineAfterCode = null): void
    {
        if ($hasNewlineAfterCode === false || !$openBracket->hasNewlineAfterCode()) {
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
