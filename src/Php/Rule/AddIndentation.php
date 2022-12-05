<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddIndentation implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if ($token->OpenedBy) {
            $token->Indent = $token->OpenedBy->Indent;

            return;
        }

        $prev          = $token->prev();
        $token->Indent = $prev->Indent;
        if (!$prev->ClosedBy) {
            return;
        }
        if ($prev->hasNewlineAfter()) {
            $prev->Tags[] = 'indented';
            $token->Indent++;
            $prev->ClosedBy->WhitespaceBefore |= WhitespaceType::LINE;

            return;
        }
        $prev->ClosedBy->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
    }
}
