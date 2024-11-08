<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Preserve newlines adjacent to operators, delimiters and comments
 *
 * @api
 */
final class PreserveNewlines implements TokenRule
{
    use TokenRuleTrait;

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
        $preserveTypeIndex = TokenTypeIndex::merge(
            $this->Idx->AllowNewlineBefore,
            $this->Idx->AllowNewlineAfter,
        );

        foreach ($tokens as $token) {
            $prev = $token->Prev;
            if (!$prev
                || $prev->line === $token->line
                || (!$preserveTypeIndex[$token->id]
                    && !$preserveTypeIndex[$prev->id])) {
                continue;
            }

            if ($prev->OriginalText === null) {
                $text = $prev->text;
            } elseif (
                $this->Idx->NotTrimmable[$prev->id]
                || $this->Idx->LeftTrimmable[$prev->id]
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
            $this->maybePreserveNewlineAfter($prev, $token, $line, $min, $max)
                // 2. If $prev moved to the next line, would a newline before it be OK?
                || ($prev->Prev && $this->maybePreserveNewlineBefore($prev, $prev->Prev, $line, $min, $max, true))
                // 3. Is a newline before $token OK?
                || $this->maybePreserveNewlineBefore($token, $prev, $line, $min, $max)
                // 4. If $token moved to the previous line, would a newline after it be OK?
                || ($token->Next && $this->maybePreserveNewlineAfter($token, $token->Next, $line, $min, $max, true));
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
        if (
            $token->line < $min
            || $token->line > $max
            || ($ignoreBrackets && $this->Idx->Bracket[$token->id])
            || !TokenUtil::isNewlineAllowedBefore($token)
        ) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if ($token->OpenedBy === $prev) {
            return false;
        }

        // Treat `?:` as one operator
        if (
            ($token->Flags & TokenFlag::TERNARY_OPERATOR)
            && $token->id === \T_COLON
            && $token->Data[TokenData::OTHER_TERNARY_OPERATOR] === $prev
        ) {
            return false;
        }

        if (!$this->Formatter->PreserveNewlines && !$token->hasNewlineBefore()) {
            return false;
        }

        if (!$this->Idx->AllowBlankBefore[$token->id]) {
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
        if (
            $next->line < $min
            || $next->line > $max
            || ($ignoreBrackets && $this->Idx->Bracket[$token->id])
            || !TokenUtil::isNewlineAllowedAfter($token)
        ) {
            return false;
        }

        // Don't preserve newlines between empty brackets
        if ($token->ClosedBy === $next) {
            return false;
        }

        // Treat `?:` as one operator
        if (
            ($token->Flags & TokenFlag::TERNARY_OPERATOR)
            && $token->id === \T_QUESTION
            && $token->Data[TokenData::OTHER_TERNARY_OPERATOR] === $next
        ) {
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
        if ($token->id === \T_COMMA
                && $token->isDelimiterBetweenMatchExpressions()
                && $token->NextCode->id === \T_DOUBLE_ARROW) {
            return false;
        }

        if ($line & WhitespaceType::BLANK
            && (!$this->Idx->AllowBlankAfter[$token->id]
                || ($token->id === \T_COMMA
                    && !$token->isDelimiterBetweenMatchArms())
                || ($token->id === \T_SEMICOLON
                    && $token->Parent
                    && $token->Parent->PrevCode
                    && $token->Parent->PrevCode->id === \T_FOR)
                || ($this->Idx->Comment[$token->id]
                    && (($token->PrevCode
                            && !$token->PrevCode->ClosedBy
                            && $token->PrevCode->EndStatement !== $token->PrevCode)
                        || ($token->Parent
                            && !($token->Parent->id === \T_OPEN_BRACE
                                && $token->Parent->Flags & TokenFlag::STRUCTURAL_BRACE)))))) {
            if (!$this->Formatter->PreserveNewlines) {
                return false;
            }
            $line = WhitespaceType::LINE;
        }

        if (!$this->Formatter->PreserveNewlines
                && !$token->hasNewlineAfter()) {
            return false;
        }

        $token->WhitespaceAfter |= $line;

        return true;
    }
}
