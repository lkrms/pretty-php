<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Preserve newlines adjacent to operators, delimiters and comments
 *
 * @api
 */
final class PreserveLineBreaks implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 93;

            default:
                return null;
        }
    }

    public function processTokens(array $tokens): void
    {
        $preserveTypeIndex = TokenType::mergeIndexes(
            $this->TypeIndex->PreserveNewlineBefore,
            $this->TypeIndex->PreserveNewlineAfter,
        );

        foreach ($tokens as $token) {
            $prev = $token->_prev;
            if (!$prev ||
                $prev->line === $token->line ||
                (!$preserveTypeIndex[$token->id] &&
                    !$preserveTypeIndex[$prev->id])) {
                continue;
            };

            $lines = $token->line - $prev->line - substr_count($prev->text, "\n");
            if (!$lines) {
                continue;
            }

            $effective = $token->effectiveWhitespaceBefore();
            if ($lines > 1) {
                if ($effective & WhitespaceType::BLANK) {
                    continue;
                }
                $line = WhitespaceType::BLANK | WhitespaceType::LINE;
            } else {
                if ($effective & (WhitespaceType::BLANK | WhitespaceType::LINE)) {
                    continue;
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
    }

    private function maybePreserveNewlineBefore(Token $token, Token $prev, int $line, int $min, int $max, bool $ignoreBrackets = false): bool
    {
        if (!$this->TypeIndex->PreserveNewlineBefore[$token->id] ||
                $token->line < $min || $token->line > $max ||
                ($ignoreBrackets && $this->TypeIndex->Bracket[$token->id])) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if (!$ignoreBrackets && $token->OpenedBy === $prev) {
            return false;
        }

        // Only preserve newlines before arrow function `=>` operators if
        // enabled
        if ($token->id === T_DOUBLE_ARROW &&
            (!$this->Formatter->NewlineBeforeFnDoubleArrows ||
                $token->prevSibling(2)->id !== T_FN)) {
            return false;
        }

        // Treat `?:` as one operator
        if ($token->IsTernaryOperator && $token->TernaryOperator1 === $prev) {
            return false;
        }

        // Don't preserve newlines before `:` other than ternary operators
        if ($token->id === T_COLON && !$token->IsTernaryOperator) {
            return false;
        }

        $token->WhitespaceBefore |=
            $this->TypeIndex->PreserveBlankBefore[$token->id]
                ? $line
                : WhitespaceType::LINE;

        return true;
    }

    private function maybePreserveNewlineAfter(Token $token, Token $next, int $line, int $min, int $max, bool $ignoreBrackets = false): bool
    {
        if ($token->id === T_ATTRIBUTE) {
            return false;
        }

        if ($token->OpenedBy && $token->OpenedBy->id === T_ATTRIBUTE) {
            $tokenId = T_ATTRIBUTE;
        }

        if (!$this->TypeIndex->PreserveNewlineAfter[$tokenId ?? $token->id] ||
                $next->line < $min || $next->line > $max ||
                ($ignoreBrackets && $this->TypeIndex->Bracket[$token->id])) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if (!$ignoreBrackets && $token->ClosedBy === $next) {
            return false;
        }

        // Don't preserve newlines after arrow function `=>` operators if
        // disabled
        if ($token->id === T_DOUBLE_ARROW &&
                $this->Formatter->NewlineBeforeFnDoubleArrows &&
                $token->prevSibling(2)->id === T_FN) {
            return false;
        }

        // Treat `?:` as one operator
        if ($token->IsTernaryOperator && $token->TernaryOperator2 === $next) {
            return false;
        }

        // Don't preserve newlines after `:` except when they terminate case
        // statements and labels
        if ($token->id === T_COLON &&
                !$token->inSwitchCase() &&
                !$token->inLabel()) {
            return false;
        }

        // Only preserve newlines after `implements` and `extends` if they are
        // followed by a list of interfaces
        if (($token->id === T_IMPLEMENTS || $token->id === T_EXTENDS) &&
            !$token->nextSiblingsWhile(...TokenType::DECLARATION_LIST)
                   ->hasOneOf(T_COMMA)) {
            return false;
        }

        if ($line & WhitespaceType::BLANK &&
            (!$this->TypeIndex->PreserveBlankAfter[$token->id] ||
                ($token->id === T_COMMA &&
                    !($next->CommentType || $token->isDelimiterBetweenMatchArms())) ||
                ($token->CommentType &&
                    $token->_prevCode &&
                    $token->_prevCode->id === T_COMMA))) {
            $line = WhitespaceType::LINE;
        }

        $token->WhitespaceAfter |= $line;
        $token->NewlineAfterPreserved = true;

        return true;
    }
}
