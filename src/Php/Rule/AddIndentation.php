<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Increase the indentation level of tokens enclosed in brackets and apply
 * symmetrical spacing to the brackets themselves
 *
 */
final class AddIndentation implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 600;
    }

    public function processToken(Token $token): void
    {
        if ($token->isCloseBracket() || $token->endsAlternativeSyntax()) {
            $token->Indent = $token->OpenedBy->Indent;

            return;
        }

        $prev          = $token->prev();
        $token->Indent = $prev->Indent;
        if (!$prev->isOpenBracket() && !$prev->startsAlternativeSyntax()) {
            return;
        }
        if ($prev->hasNewlineAfterCode()) {
            $token->Indent++;
            if (!$this->Formatter->MirrorBrackets) {
                return;
            }
            $close                    = $prev->ClosedBy;
            $close->WhitespaceBefore |= WhitespaceType::LINE;
            if (!$close->hasNewlineBefore()) {
                $close->WhitespaceMaskPrev         |= WhitespaceType::LINE;
                $close->prev()->WhitespaceMaskNext |= WhitespaceType::LINE;
            }

            return;
        }
        $prev->ClosedBy->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
    }
}
