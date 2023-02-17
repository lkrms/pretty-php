<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class PreserveNewlines implements TokenRule
{
    use TokenRuleTrait;

    public function processToken(Token $token): void
    {
        if (($prev = $token->prev())->isNull()) {
            return;
        }

        // Treat `?:` as one operator
        if ($token->isTernaryOperator()) {
            if ($token->is(':') && $prev->is('?')) {
                return;
            }
            $next = $token->next();
            if ($token->is('?') && $next->is(':')) {
                $tokenEnd = $next;
            }
        } elseif ($prev->isTernaryOperator() &&
                $prev->is(':') && ($prevPrev = $prev->prev())->is('?')) {
            $prevStart = $prevPrev;
        }

        // Don't replace non-consecutive newlines with a blank line
        if ($tokenEnd ?? $prevStart ?? null) {
            $lines = max(
                ($tokenEnd ?? null) ? $tokenEnd->line - $token->line - substr_count($token->text, "\n") : 0,
                $token->line - $prev->line - substr_count($prev->text, "\n"),
                ($prevStart ?? null) ? $prev->line - $prevStart->line - substr_count($prevStart->text, "\n") : 0
            );
        } else {
            $lines = $token->line - $prev->line - substr_count($prev->text, "\n");
        }

        if (!$lines) {
            return;
        }
        $effective = $token->effectiveWhitespaceBefore();
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

        $min = ($prevStart ?? $prev)->line;
        $max = ($tokenEnd ?? $token)->line;
        foreach ([true, false] as $noBrackets) {
            if ($this->maybePreserveNewlineAfter($prev, $token, $type, $min, $max, $noBrackets) ||
                    $this->maybePreserveNewlineBefore($token, $prev, $type, $min, $max, $noBrackets) ||
                    $this->maybePreserveNewlineBefore($prev, $prevPrev ?? $prev->prev(), $type, $min, $max, $noBrackets) ||
                    $this->maybePreserveNewlineAfter($token, $next ?? $token->next(), $type, $min, $max, $noBrackets)) {
                return;
            }
        }
    }

    private function maybePreserveNewlineBefore(Token $token, Token $prev, int $type, int $min, int $max, bool $noBrackets): bool
    {
        if ($noBrackets && $token->isCloseBracket()) {
            return false;
        }
        if (Test::isBetween($token->line, $min, $max) &&
                $token->isOneOf(...TokenType::PRESERVE_NEWLINE_BEFORE) &&
                ($noBrackets || !($token->isCloseBracket() && $prev->isOpenBracket())) &&
                (!$token->is(':') || $token->isTernaryOperator())) {
            $token->WhitespaceBefore |= $type;

            return true;
        }

        return false;
    }

    private function maybePreserveNewlineAfter(Token $token, Token $next, int $type, int $min, int $max, bool $noBrackets): bool
    {
        if ($noBrackets && $token->isOpenBracket()) {
            return false;
        }
        if (Test::isBetween($next->line, $min, $max) &&
                $token->isOneOf(...TokenType::PRESERVE_NEWLINE_AFTER) &&
                ($noBrackets || !($token->isOpenBracket() && $next->isCloseBracket())) &&
                (!$token->is(':') || $token->inSwitchCase() || $token->inLabel())) {
            $token->WhitespaceAfter |= $type;
            $token->PinToCode        = $token->PinToCode && ($type === WhitespaceType::LINE);

            return true;
        }

        return false;
    }
}
