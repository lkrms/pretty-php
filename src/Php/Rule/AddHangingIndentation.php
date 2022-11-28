<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

class AddHangingIndentation implements TokenRule
{
    public function __invoke(Token $token): void
    {
        if (!$this->isHanging($token))
        {
            return;
        }

        // If a hanging indent has already been applied to a token with the same
        // bracket stack, don't add it again
        if (in_array($token->BracketStack, $token->IndentStack, true))
        {
            return;
        }

        $current = $token;
        $until   = $token->endOfStatement();
        do
        {
            $current->Indent++;
            if ($current !== $token)
            {
                $current->IndentStack[] = $token->BracketStack;
            }
            if ($current === $until)
            {
                break;
            }
            $current = $current->next();
        }
        while (!$current->isNull());
    }

    private function isHanging(Token $token): bool
    {
        $prev = $token->prevCode();
        if ($prev === $token->prev())
        {
            if (!$prev->hasNewlineAfter())
            {
                return false;
            }
        }
        else
        {
            if (!$prev->collect($token)->hasInnerNewline())
            {
                return false;
            }
        }
        return $prev->Indent === $token->Indent &&
            !($token->isBrace() && $token->hasNewlineBefore()) &&
            !($prev->isStatementPrecursor() &&
                ($prev->parent()->isNull() || $prev->parent()->hasNewlineAfter()));
    }
}
