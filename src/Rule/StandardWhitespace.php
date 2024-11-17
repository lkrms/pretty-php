<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * Apply sensible default spacing
 *
 * @api
 */
final class StandardWhitespace implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 80,
            self::CALLBACK => 820,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return TokenTypeIndex::merge(
            [
                \T_CLOSE_TAG => true,
                \T_COMMA => true,
                \T_DECLARE => true,
                \T_MATCH => true,
                \T_START_HEREDOC => true,
            ],
            $idx->Attribute,
            $idx->OpenTag,
        );
    }

    /**
     * Apply the rule to the given tokens
     *
     * If the indentation level of an open tag aligns with a tab stop, and a
     * close tag is found in the same scope (or the document has no close tag
     * and the open tag is in the global scope), a callback is registered to
     * align nested tokens with it. An additional level of indentation is
     * applied if `IndentBetweenTags` is enabled.
     *
     * If a `<?php` tag is followed by a `declare` statement, they are collapsed
     * to one line. This is only applied in strict PSR-12 mode if the `declare`
     * statement is `declare(strict_types=1);` (semicolon optional), followed by
     * a close tag.
     *
     * Statements between open and close tags on the same line are preserved as
     * one-line statements, even if they contain constructs that would otherwise
     * break over multiple lines. Similarly, if a pair of open and close tags
     * are both adjacent to code on the same line, newlines between code and
     * tags are suppressed. Otherwise, inner newlines are added to open and
     * close tags.
     *
     * Whitespace is also applied to tokens as follows:
     *
     * - **Commas:** leading whitespace suppressed, trailing space added.
     * - **`declare` statements:** whitespace suppressed between parentheses.
     * - **`match` expressions:** trailing line added to delimiters after arms.
     * - **Attributes:** trailing blank line suppressed, leading and trailing
     *   space added for parameters, leading and trailing line added for others.
     * - **Heredocs:** leading line suppressed in strict PSR-12 mode.
     *
     * @prettyphp-callback The `TagIndent` of tokens between indented tags is
     * adjusted by the difference, if any, between the open tag's indent and the
     * indentation level of the first token after the open tag.
     */
    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            if ($token === $token->OpenTag) {
                $closeTag = null;
                $tagIndent = null;
                $innerIndent = null;

                $text = '';
                $current = $token;
                while ($current = $current->Prev) {
                    $text = $current->text . $text;
                    if ($current->id === \T_CLOSE_TAG) {
                        break;
                    }
                }

                // Get the last line of inline HTML before the open tag
                /** @var string */
                $text = strrchr("\n" . $text, "\n");
                $text = substr($text, 1);
                if ($text === '') {
                    $token->TagIndent = 0;
                } elseif (Regex::match('/^\h++$/', $text)) {
                    $indent = strlen(Str::expandTabs($text, $this->Formatter->TabSize));
                    if ($indent % $this->Formatter->TabSize === 0) {
                        $indent = (int) ($indent / $this->Formatter->TabSize);

                        // May be used by `Renderer::renderWhitespaceBefore()`,
                        // even if it isn't used here
                        $token->TagIndent = $indent;

                        // Try to find a close tag in the same scope
                        $current = $token;
                        while ($current->CloseTag) {
                            if ($current->CloseTag->Parent === $token->Parent) {
                                $closeTag = $current->CloseTag;
                                break;
                            }
                            $current = $current->CloseTag;
                            while ($current->Next) {
                                $current = $current->Next;
                                if ($current === $current->OpenTag) {
                                    continue 2;
                                }
                            }
                            break;
                        }

                        if ($closeTag || (!$token->Parent && !$current->CloseTag)) {
                            $tagIndent = $indent;
                            $innerIndent = $tagIndent;
                            if ($this->Formatter->IndentBetweenTags) {
                                $innerIndent++;
                            }
                        }
                    }
                }

                $endOfLine = $token;
                if (
                    $token->id === \T_OPEN_TAG
                    && $this->Formatter->CollapseDeclareHeaders
                    && ($declare = $token->Next)
                    && $declare->id === \T_DECLARE
                    && $declare->NextSibling
                    && ($end = $declare->NextSibling->NextSibling)
                    && $end === $declare->EndStatement
                    && (!$end->NextCode || $end->NextCode->id !== \T_DECLARE)
                ) {
                    $endIsClose = $end->id === \T_CLOSE_TAG
                        || ($end->Next && $end->Next->id === \T_CLOSE_TAG);

                    if (!$this->Formatter->Psr12 || (
                        $endIsClose
                        && !strcasecmp((string) $declare->NextSibling->inner(), 'strict_types=1')
                    )) {
                        $token->WhitespaceAfter |= WhitespaceType::SPACE;
                        $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                        $endOfLine = $end;
                        if ($endIsClose) {
                            /** @var Token */
                            $close = $token->CloseTag;
                            $close->WhitespaceBefore |= WhitespaceType::SPACE;
                            $close->WhitespaceMaskPrev = WhitespaceType::SPACE;
                        }
                    }
                }
                if ($endOfLine->id !== \T_CLOSE_TAG) {
                    $endOfLine->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
                }

                // Preserve one-line statements between open and close tags on
                // the same line
                $last = $token->CloseTag ?? $token->last();
                if (
                    $token !== $last
                    && $this->preserveOneLine($token, $last, false, true)
                ) {
                    continue;
                }

                // Suppress newlines between tags and adjacent code on the same
                // line if found at both ends
                if (
                    $token->CloseTag
                    && $token->NextCode
                    && $token->NextCode->Index < $token->CloseTag->Index
                ) {
                    $nextCode = $token->NextCode;
                    /** @var Token */
                    $lastCode = $token->CloseTag->PrevCode;
                    if (
                        $nextCode->line === $token->line
                        && $lastCode->line === $token->CloseTag->line
                    ) {
                        $this->preserveOneLine($token, $nextCode, true);
                        $this->preserveOneLine($lastCode, $token->CloseTag, true);
                        // If indentation between tags has been added, remove it
                        $innerIndent = $tagIndent;
                    }
                }

                // If indentation applied to `$token->Next` by other rules
                // differs from `$innerIndent`, apply the difference to tokens
                // between `$token` and `$closeTag`, or between `$token` and
                // `$last` if no close tag was found in the same scope
                if ($innerIndent && $token->Next) {
                    $next = $token->Next;
                    $last = $closeTag ?? $last;
                    $this->Formatter->registerCallback(
                        static::class,
                        $next,
                        static function () use ($idx, $innerIndent, $next, $last) {
                            $delta = $innerIndent - $next->indent();
                            if ($delta) {
                                foreach ($next->collect($last) as $token) {
                                    if (!$idx->OpenTag[$token->id]) {
                                        $token->TagIndent += $delta;
                                    }
                                }
                            }
                        },
                    );
                }

                continue;
            }

            if ($token->id === \T_CLOSE_TAG) {
                $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
                continue;
            }

            if ($token->id === \T_COMMA) {
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                continue;
            }

            if ($token->id === \T_DECLARE) {
                /** @var Token */
                $nextCode = $token->NextCode;
                $nextCode->outer()->maskInnerWhitespace(WhitespaceType::NONE);
                continue;
            }

            if ($token->id === \T_MATCH) {
                $parent = $token->nextSiblingOf(\T_OPEN_BRACE);
                /** @var Token */
                $arm = $parent->NextCode;
                if ($arm === $parent->ClosedBy) {
                    continue;
                }
                while (true) {
                    $arm = $arm->nextSiblingOf(\T_DOUBLE_ARROW)
                               ->nextSiblingOf(\T_COMMA);
                    if ($arm->id === \T_NULL) {
                        break;
                    }
                    $arm->WhitespaceAfter |= WhitespaceType::LINE;
                }
                continue;
            }

            if ($idx->Attribute[$token->id]) {
                /** @var Token */
                $closedBy = $token->id === \T_ATTRIBUTE
                    ? $token->ClosedBy
                    : $token;
                if (!$token->inParameterList()) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                    $closedBy->WhitespaceAfter |= WhitespaceType::LINE;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $closedBy->WhitespaceAfter |= WhitespaceType::SPACE;
                $closedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
                continue;
            }

            if ($token->id === \T_START_HEREDOC && $this->Formatter->Psr12) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            }
        }
    }
}
