<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenUtil;

/**
 * Preserve newlines in the input
 *
 * @api
 */
final class PreserveNewlines implements TokenRule
{
    use TokenRuleTrait;

    /** @var array<int,bool> */
    private array $AllowNewlineIndex;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 200,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->NotVirtual;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->AllowNewlineIndex = $this->Idx->merge(
            $this->Idx->AllowNewlineBefore,
            $this->Idx->AllowNewlineAfter,
        );
    }

    /**
     * Apply the rule to the given tokens
     *
     * If a newline in the input is adjacent to a token in `AllowNewlineBefore`
     * or `AllowNewlineAfter`, it is applied to the token as a leading or
     * trailing newline on a best-effort basis. This has the effect of placing
     * operators before or after newlines as per the formatter's token index.
     *
     * Similarly, blank lines in the input are preserved between tokens in
     * `AllowBlankBefore` and `AllowBlankAfter`, except:
     *
     * - after `:` if there is a subsequent token in the same scope
     * - after `,` other than between `match` expression arms
     * - after `;` in `for` expressions
     * - after mid-statement comments and comments in non-statement scopes
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $prev = $token->prevReal();
            if (
                !$prev
                || $prev->line === $token->line
                || (
                    !$this->AllowNewlineIndex[$token->id]
                    && !$this->AllowNewlineIndex[$prev->id]
                )
            ) {
                continue;
            }

            if ($prev->OriginalText === null) {
                $text = $prev->text;
            } elseif ($this->Idx->NotRightTrimmable[$prev->id]) {
                $text = $prev->OriginalText;
            } else {
                $text = rtrim($prev->OriginalText);
            }

            $lines = $token->line - $prev->line - substr_count($text, "\n");

            if (!$lines) {
                continue;
            }

            $before = $token->getWhitespaceBefore();
            if ($lines > 1) {
                if ($before & Space::BLANK) {
                    continue;
                }
                $line = Space::BLANK | Space::LINE;
            } else {
                if ($before & (Space::BLANK | Space::LINE)) {
                    continue;
                }
                $line = Space::LINE;
            }

            $min = $prev->line;
            $max = $token->line;
            // - Is a newline after $prev OK?
            // - If $prev moves to the next line, is a newline before it OK?
            // - Is a newline before $token OK?
            // - If $token moves to the previous line, is a newline after it OK?
            $this->maybePreserveNewlineAfter($prev, $token, $line, $min, $max)
                || ($prev->Prev && $this->maybePreserveNewlineBefore($prev, $prev->Prev, $line, $min, $max, true))
                || $this->maybePreserveNewlineBefore($token, $prev, $line, $min, $max)
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
        if ($token->OpenBracket === $prev) {
            return false;
        }

        // Treat `?:` as one operator
        if (
            ($token->Flags & TokenFlag::TERNARY)
            && $token->id === \T_COLON
            && $token->Data[TokenData::OTHER_TERNARY] === $prev
        ) {
            return false;
        }

        if (!$this->Formatter->PreserveNewlines && !$token->hasNewlineBefore()) {
            return false;
        }

        if (!$this->Idx->AllowBlankBefore[$token->id]) {
            $line = Space::LINE;
        }

        $token->Whitespace |= $line;

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
        if ($token->CloseBracket === $next) {
            return false;
        }

        // Treat `?:` as one operator
        if (
            ($token->Flags & TokenFlag::TERNARY)
            && $token->id === \T_QUESTION
            && $token->Data[TokenData::OTHER_TERNARY] === $next
        ) {
            return false;
        }

        // Don't preserve newlines between `,` and `=>` in `match` expressions
        if (
            $token->id === \T_COMMA
            && $token->isDelimiterBetweenMatchExpressions()
        ) {
            /** @var Token */
            $nextCode = $token->NextCode;
            if ($nextCode->id === \T_DOUBLE_ARROW) {
                return false;
            }
        }

        if (!$this->Formatter->PreserveNewlines && !$token->hasNewlineAfter()) {
            return false;
        }

        $parent = $token->Parent;
        if (
            $line & Space::BLANK && (
                !$this->Idx->AllowBlankAfter[$token->id]
                || (
                    $token->id === \T_COLON
                    && $next->Parent === $parent
                ) || (
                    $token->id === \T_COMMA
                    && !$token->isDelimiterBetweenMatchArms()
                ) || (
                    $token->id === \T_SEMICOLON
                    && $parent
                    && ($prev = $parent->PrevCode)
                    && $prev->id === \T_FOR
                ) || (
                    $this->Idx->Comment[$token->id] && (
                        (
                            ($prevCode = $token->PrevCode)
                            && $prevCode->Parent === $parent
                            && $prevCode->EndStatement !== $prevCode
                        ) || (
                            $parent
                            && !($parent->Flags & TokenFlag::STRUCTURAL_BRACE)
                        )
                    )
                ) || (
                    $this->Idx->Comment[$next->id] && (
                        (
                            ($prevCode = $next->PrevCode)
                            && $prevCode->Parent === $next->Parent
                            && $prevCode->EndStatement !== $prevCode
                        ) || (
                            $next->Parent
                            && !($next->Parent->Flags & TokenFlag::STRUCTURAL_BRACE)
                            && !(
                                $next->Parent->id === \T_OPEN_BRACE
                                && $next->Parent->isMatchOpenBrace()
                                && ($prevCode = $next->PrevCode)
                                && $prevCode->id === \T_COMMA
                                && $prevCode->isDelimiterBetweenMatchArms()
                            )
                        )
                    )
                )
            )
        ) {
            $line = Space::LINE;
        }

        $token->Whitespace |= $line << 3;

        return true;
    }
}
