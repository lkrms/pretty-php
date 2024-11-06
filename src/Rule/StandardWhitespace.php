<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Utility\Regex;
use Salient\Utility\Str;

/**
 * Apply sensible default spacing
 *
 * - Add SPACE as per:
 *   - {@see TokenTypeIndex::$AddSpaceAround}
 *   - {@see TokenTypeIndex::$AddSpaceBefore}
 *   - {@see TokenTypeIndex::$AddSpaceAfter}
 * - Suppress SPACE and BLANK as per:
 *   - {@see TokenTypeIndex::$SuppressSpaceAfter}
 *   - {@see TokenTypeIndex::$SuppressSpaceBefore}
 * - Suppress SPACE and BLANK after open brackets and before close brackets
 * - Propagate indentation from `<?php` tags to subsequent tokens
 * - Add SPACE or BLANK between `<?php` and a subsequent `declare` construct in
 *   the global scope
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
            $idx->CloseBracketOrEndAltSyntax,
            $idx->AddSpaceAround,
            $idx->AddSpaceBefore,
            $idx->AddSpaceAfter,
            $idx->SuppressSpaceBefore,
            $idx->SuppressSpaceAfter,
        );
    }

    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            // Add SPACE as per:
            // - {@see TokenTypeIndex::$AddSpaceAround}
            // - {@see TokenTypeIndex::$AddSpaceBefore}
            // - {@see TokenTypeIndex::$AddSpaceAfter}
            if ($idx->AddSpaceAround[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceBefore[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceAfter[$token->id]) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            }

            // - Suppress SPACE and BLANK as per:
            //   - {@see TokenTypeIndex::$SuppressSpaceAfter}
            //   - {@see TokenTypeIndex::$SuppressSpaceBefore}
            // - Suppress SPACE and BLANK after open brackets and before close
            //   brackets
            if (($idx->OpenBracket[$token->id] && !(
                $token->Flags & TokenFlag::STRUCTURAL_BRACE
                || $token->isMatchBrace()
            ))
                    || $idx->SuppressSpaceAfter[$token->id]) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_COLON && $token->ClosedBy) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            }

            if (($idx->CloseBracket[$token->id] && !(
                $token->Flags & TokenFlag::STRUCTURAL_BRACE
                || $token->isMatchBrace()
            ))
                || ($idx->SuppressSpaceBefore[$token->id]
                    // Only suppress SPACE before namespace separators in or
                    // immediately after an identifier
                    && ($token->id !== \T_NS_SEPARATOR || ($token->Prev && (
                        $token->Prev->id === \T_NAME_FULLY_QUALIFIED
                        || $token->Prev->id === \T_NAME_QUALIFIED
                        || $token->Prev->id === \T_NAME_RELATIVE
                        || $token->Prev->id === \T_STRING
                    ))))) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_END_ALT_SYNTAX) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            }

            if ($token === $token->OpenTag) {
                // Propagate indentation from `<?php` tags to subsequent tokens
                $tagIndent = 0;
                /** @var Token|null */
                $sibling = null;
                $siblingIndent = null;

                $text = '';
                $current = $token;
                while ($current = $current->Prev) {
                    $text = $current->text . $text;
                    if ($current->id === \T_CLOSE_TAG) {
                        break;
                    }
                }

                // Only perform pattern matching on the last line
                /** @var string */
                $text = strrchr("\n" . $text, "\n");
                $text = substr($text, 1);
                if (Regex::match('/^\h++$/', $text)) {
                    $indent = strlen(Str::expandTabs($text, $this->Formatter->TabSize));
                    if ($indent % $this->Formatter->TabSize === 0) {
                        $token->TagIndent = (int) ($indent / $this->Formatter->TabSize);

                        /*
                         * Look for a `?>` tag in the same context, i.e. with
                         * the same parent
                         */
                        $current = $token;
                        while ($current->CloseTag) {
                            if ($current->CloseTag->Parent === $token->Parent) {
                                $sibling = $current->CloseTag;
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

                        if ($sibling || (!$token->Parent && !$token->CloseTag)) {
                            $tagIndent = $token->TagIndent;
                            if ($sibling) {
                                $siblingIndent = $tagIndent;
                            }
                            // Increase the indentation level for tokens between
                            // unenclosed tags
                            if (
                                !$token->Parent
                                && $this->Formatter->IncreaseIndentBetweenUnenclosedTags
                            ) {
                                $tagIndent++;
                            }
                        }
                    }
                }

                // Add SPACE or BLANK between `<?php` and a subsequent `declare`
                // construct in the global scope
                $current = $token;
                if (
                    $this->Formatter->CollapseDeclareHeaders
                    && $token->id === \T_OPEN_TAG
                    && ($declare = $token->Next)
                    && $declare->id === \T_DECLARE
                    && $declare->NextSibling
                    && ($end = $declare->NextSibling->NextSibling)
                    && $end === $declare->EndStatement
                    && (!$end->NextCode || $end->NextCode->id !== \T_DECLARE)
                    && (
                        !$this->Formatter->Psr12 || (
                            !strcasecmp((string) $declare->NextSibling->inner(), 'strict_types=1')
                            && ($end->id === \T_CLOSE_TAG || ($end->Next && $end->Next->id === \T_CLOSE_TAG))
                        )
                    )
                ) {
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext = WhitespaceType::SPACE;
                    $current = $end;
                    if (
                        $this->Formatter->Psr12
                        || $end->id === \T_CLOSE_TAG
                        || ($end->Next && $end->Next->id === \T_CLOSE_TAG)
                    ) {
                        assert($token->CloseTag !== null);
                        $token->CloseTag->WhitespaceBefore |= WhitespaceType::SPACE;
                        $token->CloseTag->WhitespaceMaskPrev = WhitespaceType::SPACE;
                    }
                }
                // Add LINE|SPACE after `<?php`
                $current->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;

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
                            $tagIndent
                            && !$token->Parent
                            && $this->Formatter->IncreaseIndentBetweenUnenclosedTags
                        ) {
                            $tagIndent--;
                        }
                    }
                }

                // If the level of indentation applied to `$token->Next` by
                // other rules is less than `$tagIndent`, apply the difference
                // to tokens between `$token` and `$sibling`, or between
                // `$token` and `$token->CloseTag ?: $token->last()` if no
                // `$sibling` was found
                if ($tagIndent && $token->Next) {
                    $next = $token->Next;
                    /** @var Token */
                    $last = $sibling ? $sibling->Prev : $last;
                    $this->Formatter->registerCallback(
                        static::class,
                        $next,
                        function () use ($tagIndent, $next, $last, $sibling, $siblingIndent) {
                            $delta = $tagIndent - $next->indent();
                            if ($delta > 0) {
                                foreach ($next->collect($last) as $token) {
                                    $token->TagIndent += $delta;
                                }
                            }
                            if ($sibling) {
                                $sibling->TagIndent += $siblingIndent - $sibling->indent();
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
