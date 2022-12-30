<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class AddIndentation implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if ($token->isCloseBracket() || $token->endsAlternativeSyntax()) {
            $token->Indent = $token->OpenedBy->Indent;

            return;
        }

        $prev          = $token->prev();
        $token->Indent = $prev->Indent;
        if (!($prev->isOpenBracket() || $prev->startsAlternativeSyntax())) {
            return;
        }
        if ($prev->hasNewlineAfterCode()) {
            $token->Indent++;
            $prev->ClosedBy->WhitespaceBefore |= WhitespaceType::LINE;

            return;
        }
        $prev->ClosedBy->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
    }
}
