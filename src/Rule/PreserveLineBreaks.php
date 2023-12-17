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

    public static function getPriority(string $method): ?int
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
            }

            if ($prev->OriginalText === null) {
                $text = $prev->text;
            } elseif (
                $this->TypeIndex->DoNotModify[$prev->id] ||
                $this->TypeIndex->DoNotModifyRight[$prev->id]
            ) {
                $text = $prev->OriginalText;
            } else {
                $text = rtrim($prev->OriginalText);
            }

            $lines = $token->line - $prev->line - substr_count($text, "\n");

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
            // 1. Is a newline after $prev OK?
            $this->maybePreserveNewlineAfter($prev, $token, $line, $min, $max) ||
                // 2. If $prev moved to the next line, would a newline before it be OK?
                $this->maybePreserveNewlineBefore($prev, $prev->prev(), $line, $min, $max, true) ||
                // 3. Is a newline before $token OK?
                $this->maybePreserveNewlineBefore($token, $prev, $line, $min, $max) ||
                // 4. If $token moved to the previous line, would a newline after it be OK?
                $this->maybePreserveNewlineAfter($token, $token->next(), $line, $min, $max, true);
        }
    }

    private function maybePreserveNewlineBefore(
        Token $token,
        Token $prev,
        int $line,
        int $min,
        int $max,
        bool $ignoreBrackets = false
    ): bool {
        if (!$this->TypeIndex->PreserveNewlineBefore[$token->id] ||
                $token->line < $min ||
                $token->line > $max ||
                ($ignoreBrackets && $this->TypeIndex->Bracket[$token->id])) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if (!$ignoreBrackets && $token->OpenedBy === $prev) {
            return false;
        }

        // Only preserve newlines before arrow function `=>` operators if
        // enabled
        if ($token->id === \T_DOUBLE_ARROW &&
            (!$this->Formatter->NewlineBeforeFnDoubleArrows ||
                $token->prevSiblingOf(\T_FN)->nextSiblingOf(\T_DOUBLE_ARROW) !== $token)) {
            return false;
        }

        // Treat `?:` as one operator
        if ($token->TernaryOperator1 === $prev) {
            return false;
        }

        // Don't preserve newlines before `:` other than ternary operators
        if ($token->id === \T_COLON && !$token->IsTernaryOperator) {
            return false;
        }

        if (!$this->Formatter->PreserveLineBreaks &&
                !$token->hasNewlineBefore()) {
            return false;
        }

        if (!$this->TypeIndex->PreserveBlankBefore[$token->id]) {
            $line = WhitespaceType::LINE;
        }

        $token->WhitespaceBefore |= $line;

        return true;
    }

    private function maybePreserveNewlineAfter(
        Token $token,
        Token $next,
        int $line,
        int $min,
        int $max,
        bool $ignoreBrackets = false
    ): bool {
        // To preserve newlines after attributes, ignore T_ATTRIBUTE itelf and
        // treat attribute close brackets as T_ATTRIBUTE
        if ($token->id === \T_ATTRIBUTE) {
            return false;
        }

        if ($token->OpenedBy && $token->OpenedBy->id === \T_ATTRIBUTE) {
            $tokenId = \T_ATTRIBUTE;
        }

        if (!$this->TypeIndex->PreserveNewlineAfter[$tokenId ?? $token->id] ||
                $next->line < $min ||
                $next->line > $max ||
                ($ignoreBrackets && $this->TypeIndex->Bracket[$token->id])) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if (!$ignoreBrackets && $token->ClosedBy === $next) {
            return false;
        }

        // Treat `?:` as one operator
        if ($token->TernaryOperator2 === $next) {
            return false;
        }

        if ($token->id === \T_CLOSE_BRACE &&
                !$token->isStructuralBrace(false)) {
            return false;
        }

        // Don't preserve newlines after `:` except when they terminate case
        // statements and labels
        if ($token->id === \T_COLON && !$token->isColonStatementDelimiter()) {
            return false;
        }

        // Don't preserve newlines after arrow function `=>` operators if
        // disabled
        if ($token->id === \T_DOUBLE_ARROW &&
                $this->Formatter->NewlineBeforeFnDoubleArrows &&
                $token->prevSiblingOf(\T_FN)->nextSiblingOf(\T_DOUBLE_ARROW) === $token) {
            return false;
        }

        // Only preserve newlines after `implements` and `extends` if they are
        // followed by a list of interfaces
        if (($token->id === \T_IMPLEMENTS ||
                    $token->id === \T_EXTENDS) &&
                !$token->IsListParent) {
            return false;
        }

        // Don't preserve newlines between `,` and `=>` in `match` expressions:
        //
        // ```
        // match ($a) {
        //     0,
        //     => false,
        // };
        // ```
        if ($token->id === \T_COMMA &&
                $token->isDelimiterBetweenMatchExpressions() &&
                $token->_nextCode->id === \T_DOUBLE_ARROW) {
            return false;
        }

        if ($line & WhitespaceType::BLANK &&
            (!$this->TypeIndex->PreserveBlankAfter[$token->id] ||
                ($token->id === \T_COMMA &&
                    !$token->isDelimiterBetweenMatchArms()) ||
                ($token->id === \T_SEMICOLON &&
                    $token->Parent &&
                    $token->Parent->_prevCode &&
                    $token->Parent->_prevCode->id === \T_FOR) ||
                ($token->CommentType &&
                    (($token->_prevCode &&
                            !$token->_prevCode->ClosedBy &&
                            $token->_prevCode->EndStatement !== $token->_prevCode) ||
                        ($token->Parent &&
                            !($token->Parent->id === \T_OPEN_BRACE &&
                                $token->Parent->isStructuralBrace(false))))))) {
            if (!$this->Formatter->PreserveLineBreaks) {
                return false;
            }
            $line = WhitespaceType::LINE;
        }

        if (!$this->Formatter->PreserveLineBreaks &&
                !$token->hasNewlineAfter()) {
            return false;
        }

        $token->WhitespaceAfter |= $line;
        $token->NewlineAfterPreserved = true;

        return true;
    }
}
