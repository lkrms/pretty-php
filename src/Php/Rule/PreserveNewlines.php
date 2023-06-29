<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;
use Lkrms\Utility\Test;

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
        if (!($prev = $token->_prev)) {
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
        # 1. Is a newline after $prev OK?
        $this->maybePreserveNewlineAfter($prev, $token, $line, $min, $max) ||
            # 2. Is a newline before $token OK?
            $this->maybePreserveNewlineBefore($token, $prev, $line, $min, $max) ||
            # 3. If $prev moved to the next line, would a newline before it be OK?
            $this->maybePreserveNewlineBefore($prev, $prev->prev(), $line, $min, $max, true) ||
            # 4. If $token moved to the previous line, would a newline after it be OK?
            $this->maybePreserveNewlineAfter($token, $token->next(), $line, $min, $max, true);
    }

    private function maybePreserveNewlineBefore(Token $token, Token $prev, int $line, int $min, int $max, bool $ignoreBrackets = false): bool
    {
        if ($ignoreBrackets && $token->isBracket()) {
            return false;
        }
        if (Test::isBetween($token->line, $min, $max) &&
                $token->is(TokenType::PRESERVE_NEWLINE_BEFORE) &&
                // Don't preserve newlines between empty brackets
                ($ignoreBrackets || !($token->isCloseBracket() && $prev->isOpenBracket())) &&
                // Treat `?:` as one operator
                (!$token->IsTernaryOperator || $token->TernaryOperator1 !== $prev) &&
                ($token->id !== T[':'] || $token->IsTernaryOperator)) {
            if (!$token->is(TokenType::PRESERVE_BLANK_BEFORE)) {
                $line = WhitespaceType::LINE;
            }
            $token->WhitespaceBefore |= $line;

            return true;
        }

        return false;
    }

    private function maybePreserveNewlineAfter(Token $token, Token $next, int $line, int $min, int $max, bool $ignoreBrackets = false): bool
    {
        if ($ignoreBrackets && $token->isBracket()) {
            return false;
        }
        if (Test::isBetween($next->line, $min, $max) &&
                $token->is(TokenType::PRESERVE_NEWLINE_AFTER) &&
                // Don't preserve newlines between empty brackets
                ($ignoreBrackets || !($token->isOpenBracket() && $next->isCloseBracket())) &&
                // Treat `?:` as one operator
                (!$token->IsTernaryOperator || $token->TernaryOperator2 !== $next) &&
                ($token->id !== T[':'] || $token->inSwitchCase() || $token->inLabel()) &&
                // Only preserve newlines after `implements` and `extends` if
                // they are followed by a list of interfaces
                (!$token->is([T_IMPLEMENTS, T_EXTENDS]) || $token->nextSiblingsWhile(...TokenType::DECLARATION_LIST)->hasOneOf(T[',']))) {
            if (!$token->is(TokenType::PRESERVE_BLANK_AFTER) ||
                    ($token->id === T[','] && !$next->is(TokenType::COMMENT)) ||
                    ($token->is(TokenType::COMMENT) && $token->prevCode()->id === T[','])) {
                $line = WhitespaceType::LINE;
            }
            $token->WhitespaceAfter |= $line;
            $token->PinToCode = $token->PinToCode && ($line === WhitespaceType::LINE);
            $token->NewlineAfterPreserved = true;

            return true;
        }

        return false;
    }
}
