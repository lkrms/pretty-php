<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Preserve newlines adjacent to operators, delimiters and comments
 *
 */
final class PreserveNewlines implements TokenRule
{
    use TokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 93;
    }

    public function processToken(Token $token): void
    {
        if (($prev = $token->prev())->IsNull) {
            return;
        }

        $lines = $token->line - $prev->line - substr_count($prev->text, "\n");
        if (!$lines) {
            return;
        }
        $effective = $token->effectiveWhitespaceBefore();
        if ($lines > 1) {
            if ($effective & WhitespaceType::BLANK) {
                return;
            }
            $line = WhitespaceType::BLANK | WhitespaceType::LINE;
        } else {
            if ($effective & (WhitespaceType::LINE | WhitespaceType::BLANK)) {
                return;
            }
            $line = WhitespaceType::LINE;
        }

        $min = $prev->line;
        $max = $token->line;
        $this->maybePreserveNewlineAfter($prev, $token, $line, $min, $max, false) ||
            $this->maybePreserveNewlineBefore($token, $prev, $line, $min, $max, false) ||
            $this->maybePreserveNewlineBefore($prev, $prev->prev(), $line, $min, $max, true) ||
            $this->maybePreserveNewlineBefore($prev, $prev->prev(), $line, $min, $max, false) ||
            $this->maybePreserveNewlineAfter($token, $token->next(), $line, $min, $max, true) ||
            $this->maybePreserveNewlineAfter($token, $token->next(), $line, $min, $max, false);
    }

    private function maybePreserveNewlineBefore(Token $token, Token $prev, int $line, int $min, int $max, bool $noBrackets): bool
    {
        if ($noBrackets && $token->isCloseBracket()) {
            return false;
        }
        if (Test::isBetween($token->line, $min, $max) &&
                $token->is(TokenType::PRESERVE_NEWLINE_BEFORE) &&
                ($noBrackets || !($token->isCloseBracket() && $prev->isOpenBracket())) &&
                // Treat `?:` as one operator
                (!$token->IsTernaryOperator || $token->TernaryOperator1 !== $prev) &&
                (!$token->is(T[':']) || $token->IsTernaryOperator)) {
            if (!$token->is(TokenType::PRESERVE_BLANK_BEFORE)) {
                $line = WhitespaceType::LINE;
            }
            $token->WhitespaceBefore |= $line;

            return true;
        }

        return false;
    }

    private function maybePreserveNewlineAfter(Token $token, Token $next, int $line, int $min, int $max, bool $noBrackets): bool
    {
        if ($noBrackets && $token->isOpenBracket()) {
            return false;
        }
        if (Test::isBetween($next->line, $min, $max) &&
                $token->is(TokenType::PRESERVE_NEWLINE_AFTER) &&
                ($noBrackets || !($token->isOpenBracket() && $next->isCloseBracket())) &&
                // Treat `?:` as one operator
                (!$token->IsTernaryOperator || $token->TernaryOperator2 !== $next) &&
                (!$token->is(T[':']) || $token->inSwitchCase() || $token->inLabel())) {
            if (!$token->is(TokenType::PRESERVE_BLANK_AFTER) ||
                    ($token->id === T[','] && !$next->is(TokenType::COMMENT)) ||
                    ($token->is(TokenType::COMMENT) && $token->prevCode()->id === T[','])) {
                $line = WhitespaceType::LINE;
            }
            $token->WhitespaceAfter |= $line;
            $token->PinToCode        = $token->PinToCode && ($line === WhitespaceType::LINE);

            return true;
        }

        return false;
    }
}
