<?php declare(strict_types=1);

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
        if ($prevTernary && $tokenTernary && $prev->Type . $token->Type === '?:') {
            // Don't check for newlines between `?` and `:`
            return;
        }
        $effective = $token->effectiveWhitespaceBefore();
        if ($tokenTernary && $token->Type . $token->next()->Type === '?:') {
            // Check for newlines between $prev and `:`
            $tokenOrig = $token;
            $token     = $token->next();
        } elseif ($prevTernary && $prev->prev()->Type . $prev->Type === '?:') {
            // Check for newlines between `?` and $token
            $prev = $prev->prev();
        }

        if ($prev->isNull() || !($lines = $token->Line - $prev->Line - substr_count($prev->Code, "\n"))) {
            return;
        }
        if ($lines > 1) {
            if ($effective & WhitespaceType::BLANK) {
                return;
            }
            $type = WhitespaceType::BLANK;
        } else {
            if ($effective & (WhitespaceType::LINE | WhitespaceType::BLANK)) {
                return;
            }
            $type = WhitespaceType::LINE;
        }
        [$min, $max] = [$prev->Line, $token->Line];
        $this->maybeAddNewline($prev, $token, $tokenOrig ?? null, $type, $min, $max) ||
            $this->maybeAddNewline($prev->prev(), $prev, null, $type, $min, $max);
    }

    private function maybeAddNewline(Token $token1, Token $token2, ?Token $token2Orig, int $whitespaceType, int $min, int $max): bool
    {
        if (!Test::isBetween($token1->Line, $min, $max) ||
            !Test::isBetween($token2->Line, $min, $max) ||
            ($token1->effectiveWhitespaceAfter() & $whitespaceType) === $whitespaceType) {
            return false;
        }
        if ($this->preserveNewlineAfter($token1)) {
            $token1->WhitespaceAfter |= $whitespaceType;

            return true;
        }
        if ($this->preserveNewlineBefore($token2)) {
            $token2                    = $token2Orig ?: $token2;
            $token2->WhitespaceBefore |= $whitespaceType;

            return true;
        }

        return false;
    }

    private function preserveNewlineAfter(Token $token): bool
    {
        return $token->isOneOf(...TokenType::PRESERVE_NEWLINE_AFTER) &&
            !($token->isOpenBracket() && $token->next()->isCloseBracket()) &&
            (!$token->is(':') || $token->isTernaryOperator());
    }

    private function preserveNewlineBefore(Token $token): bool
    {
        return $token->isOneOf(...TokenType::PRESERVE_NEWLINE_BEFORE) &&
            !($token->isCloseBracket() && $token->prev()->isOpenBracket()) &&
            (!$token->is(':') || $token->isTernaryOperator());
    }
}
