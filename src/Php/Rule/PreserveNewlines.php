<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class PreserveNewlines implements TokenRule
{
    public function __invoke(Token $token): void
    {
        $prev = $token->prev();

        // Treat `?:` as one operator (TODO: make this simpler)
        [$prevTernary, $tokenTernary] = [
            $prev->isTernaryOperator(),
            $token->isTernaryOperator(),
        ];
        if ($prevTernary && $tokenTernary && $prev->Type . $token->Type === "?:")
        {
            // Don't check for newlines between `?` and `:`
            return;
        }
        $effective = $token->effectiveWhitespaceBefore();
        if ($tokenTernary && $token->Type . $token->next()->Type === "?:")
        {
            // Check for newlines between $prev and `:`
            $token = $token->next();
        }
        elseif ($prevTernary && $prev->prev()->Type . $prev->Type === "?:")
        {
            // Check for newlines between `?` and $token
            $prev = $prev->prev();
        }

        if ($prev->isNull() || !($lines = $token->Line - $prev->Line - substr_count($prev->Code, "\n")))
        {
            return;
        }
        if ($lines > 1)
        {
            if ($effective & WhitespaceType::BLANK)
            {
                return;
            }
            $type = WhitespaceType::BLANK;
        }
        else
        {
            if ($effective & (WhitespaceType::LINE | WhitespaceType::BLANK))
            {
                return;
            }
            $type = WhitespaceType::LINE;
        }
        [$min, $max] = [$prev->Line, $token->Line];
        $this->maybeAddNewline($prev, $token, $type, $min, $max) ||
            $this->maybeAddNewline($prev->prev(), $prev, $type, $min, $max);
    }

    private function maybeAddNewline(Token $token1, Token $token2, int $whitespaceType, int $min, int $max): bool
    {
        if (!Test::isBetween($token1->Line, $min, $max) ||
            !Test::isBetween($token2->Line, $min, $max) ||
            $token1->hasNewlineAfter())
        {
            return false;
        }
        if ($token1->isOneOf(...TokenType::PRESERVE_NEWLINE_AFTER))
        {
            $token1->WhitespaceAfter |= $whitespaceType;

            return true;
        }
        if ($token2->isOneOf(...TokenType::PRESERVE_NEWLINE_BEFORE))
        {
            $token2->WhitespaceBefore |= $whitespaceType;

            return true;
        }

        return false;
    }
}
