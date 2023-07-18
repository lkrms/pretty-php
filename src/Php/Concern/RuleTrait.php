<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Support\TokenTypeIndex;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

trait RuleTrait
{
    /**
     * @var Formatter
     */
    protected $Formatter;

    /**
     * @var TokenTypeIndex
     */
    protected $TypeIndex;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->TypeIndex = $formatter->TokenTypeIndex;
    }

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

    public function reset(): void {}
}
