<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
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
 * - Add LINE|SPACE after `<?php` and before `?>`
 * - Preserve one-statement `<?php` ... `?>` blocks on the same line, or
 *   suppress inner LINE between tags and code if both ends have adjacent code
 * - Add SPACE after and suppress SPACE before commas
 * - Add LINE after labels
 * - Add LINE between the arms of match expressions
 * - Add SPACE before and after parameter attributes, LINE and SPACE before and
 *   after other attributes, and suppress BLANK after all attributes
 * - Suppress whitespace inside `declare()`
 * - In strict PSR-12 mode, suppress BLANK and LINE before heredocs
 */
final class StandardWhitespace implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 80;

            case self::CALLBACK:
                return 820;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return TokenTypeIndex::merge(
            TokenTypeIndex::get(
                \T_COLON,
                \T_COMMA,
                \T_MATCH,
                \T_OPEN_TAG,
                \T_OPEN_TAG_WITH_ECHO,
                \T_CLOSE_TAG,
                \T_ATTRIBUTE_COMMENT,
                \T_START_HEREDOC,
            ),
            $idx->OpenBracket,
            $idx->CloseBracketOrAlt,
            $idx->AddSpace,
            $idx->AddSpaceBefore,
            $idx->AddSpaceAfter,
            $idx->SuppressSpaceBefore,
            $idx->SuppressSpaceAfter,
        );
    }

    /**
     * Leading and trailing spaces are added to tokens in `AddSpace`,
     * `AddSpaceBefore` and `AddSpaceAfter`, then suppressed, along with blank
     * lines, for tokens in `SuppressSpaceBefore` and `SuppressSpaceAfter`, and
     * inside brackets other than structural and `match` braces. Blank lines are
     * also suppressed after alternative syntax colons and before their closing
     * counterparts.
     *
     * If the indentation level of an open tag aligns with a tab stop, and a
     * close tag is found in the same scope (or the document has no close tag
     * and the open tag is in the global scope), nested tokens are indented to
     * align with it. In the global scope, an additional level of indentation is
     * applied unless `MatchIndentBetweenGlobalTags` is enabled.
     *
     * If a `<?php` tag is followed by a `declare` statement, they are collapsed
     * to one line. This is only applied in strict PSR-12 mode if the `declare`
     * statement is `declare(strict_types=1);` (semicolon optional), followed by
     * a close tag.
     */
    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            if ($idx->AddSpace[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceBefore[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceAfter[$token->id]) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            }

            if ($idx->SuppressSpaceAfter[$token->id] || (
                $idx->OpenBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || $token->isMatchBrace()
                )
            )) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_COLON && $token->ClosedBy) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            }

            if ($idx->SuppressSpaceBefore[$token->id] || (
                $idx->CloseBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || $token->isMatchBrace()
                )
            )) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_END_ALT_SYNTAX) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            }

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
                            // Increase the indentation level for tokens between
                            // tags in the global scope
                            if (
                                !$token->Parent
                                && !$this->Formatter->MatchIndentBetweenGlobalTags
                            ) {
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

                /*
                 * Preserve one-statement `<?php` ... `?>` blocks on the same
                 * line
                 */
                $last = $token->CloseTag ?: $token->last();
                if (
                    $token !== $last
                    && $this->preserveOneLine($token, $last, false, true)
                ) {
                    continue;
                }

                // Suppress inner LINE between tags and code if both ends have
                // adjacent code
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
                        // Remove a level of indentation if tokens between
                        // unenclosed tags don't start on a new line
                        if (
                            $innerIndent
                            && !$token->Parent
                            && !$this->Formatter->MatchIndentBetweenGlobalTags
                        ) {
                            $innerIndent--;
                        }
                    }
                }

                // If the level of indentation applied to `$token->Next` by
                // other rules is less than `$innerIndent`, apply the difference
                // to tokens between `$token` and `$sibling`, or between
                // `$token` and `$token->CloseTag ?: $token->last()` if no
                // `$sibling` was found
                if ($innerIndent && $token->Next) {
                    $next = $token->Next;
                    /** @var Token */
                    $last = $closeTag ? $closeTag->Prev : $last;
                    $this->Formatter->registerCallback(
                        static::class,
                        $next,
                        function () use ($innerIndent, $next, $last, $closeTag, $tagIndent) {
                            $delta = $innerIndent - $next->indent();
                            if ($delta > 0) {
                                foreach ($next->collect($last) as $token) {
                                    if (!$this->Idx->OpenTag[$token->id]) {
                                        $token->TagIndent += $delta;
                                    }
                                }
                            }
                            if ($closeTag) {
                                $closeTag->TagIndent += $tagIndent - $closeTag->indent();
                            }
                        }
                    );
                }

                continue;
            }

            /* Add LINE|SPACE before `?>` */
            if ($token->id === \T_CLOSE_TAG) {
                $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
                continue;
            }

            // Add SPACE after and suppress SPACE before commas
            if ($token->id === \T_COMMA) {
                $token->WhitespaceMaskPrev = WhitespaceType::NONE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                continue;
            }

            // Add LINE after labels
            if (
                $token->id === \T_COLON
                && $token->getSubType() === TokenSubType::COLON_LABEL_DELIMITER
            ) {
                $token->WhitespaceAfter |= WhitespaceType::LINE;
                continue;
            }

            // Add LINE between the arms of match expressions
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

            // Add SPACE before and after parameter attributes, LINE and SPACE
            // before and after other attributes, and suppress BLANK after all
            // attributes
            if ($token->id === \T_ATTRIBUTE) {
                assert($token->ClosedBy !== null);
                if (!$token->inParameterList()) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                    $token->ClosedBy->WhitespaceAfter |= WhitespaceType::LINE;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->ClosedBy->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
                continue;
            }
            if ($token->id === \T_ATTRIBUTE_COMMENT) {
                if (!$token->inParameterList()) {
                    $token->WhitespaceBefore |= WhitespaceType::LINE;
                    $token->WhitespaceAfter |= WhitespaceType::LINE;
                }
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
                continue;
            }

            // Suppress whitespace inside `declare()`
            if (
                $token->id === \T_OPEN_PARENTHESIS
                && $token->PrevCode
                && $token->PrevCode->id === \T_DECLARE
            ) {
                $token->outer()->maskInnerWhitespace(WhitespaceType::NONE);
                continue;
            }

            // In strict PSR-12 mode, suppress BLANK and LINE before heredocs
            if ($token->id === \T_START_HEREDOC && $this->Formatter->Psr12) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            }
        }
    }
}
