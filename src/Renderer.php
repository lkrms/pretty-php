<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\Utility\Regex;
use Salient\Utility\Str;

final class Renderer implements Immutable
{
    use HasMutator;

    private Formatter $Formatter;
    private string $SoftTab;
    private string $Tab;
    private TokenTypeIndex $Idx;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->SoftTab = $formatter->SoftTab;
        $this->Tab = $formatter->Tab;
        $this->Idx = $formatter->TokenTypeIndex;
    }

    /**
     * Get an instance with the given formatter
     *
     * @return static
     */
    public function withFormatter(Formatter $formatter): self
    {
        return $this->with('Formatter', $formatter)
                    ->with('SoftTab', $formatter->SoftTab)
                    ->with('Tab', $formatter->Tab)
                    ->with('Idx', $formatter->TokenTypeIndex);
    }

    public function render(
        Token $from,
        Token $to,
        bool $softTabs = false,
        bool $setPosition = false,
        bool $final = false
    ): string {
        $indentMayHaveTabs = !$softTabs && $this->Tab === "\t";

        $t = $from;
        $code = '';
        do {
            // Render whitespace before the token
            $before = $this->renderWhitespaceBefore($t, $softTabs);

            // If whitespace after the token will not be rendered on the next
            // iteration, render it now
            $after = (
                !$t->Next
                || $this->Idx->NotTrimmable[$t->Next->id]
                || $this->Idx->RightTrimmable[$t->Next->id]
            )
                ? $this->renderWhitespaceAfter($t)
                : '';

            if ($setPosition) {
                if (!$t->Prev) {
                    $t->OutputLine = 1;
                    $t->OutputColumn = 1;
                    $t->OutputPos = 0;
                }

                // Adjust the token's position to account for any leading
                // whitespace
                if ($before !== '') {
                    $this->setPosition($t, $before, $indentMayHaveTabs);
                }

                // And use it as the baseline for the next token's position
                if ($t->Next) {
                    $t->Next->OutputLine = $t->OutputLine;
                    $t->Next->OutputColumn = $t->OutputColumn;
                    $t->Next->OutputPos = $t->OutputPos;
                }
            }

            if (
                $final
                && $this->Idx->Comment[$t->id]
                && strpos($t->text, "\n") !== false
            ) {
                $text = $this->getMultiLineComment($t, $softTabs);
            } elseif ((
                $t->id === \T_START_HEREDOC
                || ($t->Heredoc && $t->id !== \T_END_HEREDOC)
            ) && ($heredoc = $t->Heredoc ?? $t)->HeredocIndent) {
                $text = Regex::replace(
                    ($t->Next->text[0] ?? null) === "\n"
                        ? "/\\n{$heredoc->HeredocIndent}\$/m"
                        : "/\\n{$heredoc->HeredocIndent}(?=\\n)/",
                    "\n",
                    $t->text,
                );
            } else {
                $text = $t->text;
            }

            $output = $text . $after;
            if ($setPosition && $t->Next && $output !== '') {
                $this->setPosition(
                    $t->Next,
                    $output,
                    $this->Idx->Expandable[$t->id]
                );
            }
            $code .= $before . $output;
        } while ($t !== $to && ($t = $t->Next));

        return $code;
    }

    public function renderWhitespaceBefore(
        Token $token,
        bool $softTabs = false
    ): string {
        if (
            $this->Idx->NotTrimmable[$token->id]
            || $this->Idx->RightTrimmable[$token->id]
        ) {
            return '';
        }

        $before = '';
        $padding = $token->Padding;
        if ($whitespace = $token->effectiveWhitespaceBefore()) {
            $before = TokenUtil::getWhitespace($whitespace);
            if ($before[0] === "\n") {
                $indent = $token->indent();
                $padding += $token->LinePadding - $token->LineUnpadding;

                // Don't indent close tags unless subsequent text is indented by
                // at least the same amount
                if ($token->id === \T_CLOSE_TAG && (
                    !$token->Next
                    || $this->getIndentSpacesFromText($token->Next)
                        < $this->getIndentSpaces($token)
                )) {
                    /** @var Token */
                    $openTag = $token->OpenTag;
                    // Look for an indented open tag at the same depth as the
                    // close tag if its own open tag differs or is not indented
                    if (
                        $token->Depth !== $openTag->Depth
                        || $token->TagIndent === null
                    ) {
                        $fallback = null;
                        $current = $token;
                        while ($current->Next) {
                            if ($current === $current->OpenTag) {
                                if ($current->CloseTag) {
                                    $current = $current->CloseTag;
                                    continue;
                                }
                                break;
                            }
                            $current = $current->Next;
                            if ($current !== $current->OpenTag) {
                                continue;
                            }
                            if (
                                $current->Depth !== $token->Depth
                                || $current->TagIndent === null
                                || $current->TagIndent * $this->Formatter->TabSize
                                    > $this->getIndentSpaces($token)
                            ) {
                                continue;
                            }

                            /*
                             * Use standard indentation if this open tag has an
                             * adjacent close bracket and the close tag isn't
                             * adjacent to its open bracket, as in line 5:
                             *
                             * ```
                             * function foo()
                             * {
                             *     if ($bar) {
                             *         // do stuff
                             *         ?>
                             *     <?php } else { ?>
                             *         <!-- output stuff -->
                             *         <?php
                             *     }
                             * }
                             * ```
                             */
                            if (
                                $current->Next
                                && $current->Next->OpenedBy
                                && $token->Prev !== $current->Next->OpenedBy
                            ) {
                                $openTag = null;
                                break;
                            }

                            // If the adjacent brackets of this open tag don't
                            // mirror the close tag's, save it in case an open
                            // tag that does is found
                            if (
                                !$fallback
                                && $this->tagHasAdjacentBracket($current)
                                    !== $this->tagHasAdjacentBracket($token)
                            ) {
                                $fallback = $current;
                                continue;
                            }

                            // Otherwise, use it for indentation
                            $openTag = $current;
                            break;
                        }
                        if ($openTag === $token->OpenTag && $fallback) {
                            $openTag = $fallback;
                        }
                    }
                    if ($openTag && $openTag->TagIndent !== null) {
                        $indent = $openTag->TagIndent;
                        $padding = 0;
                    }
                }

                if ($indent) {
                    $before .= str_repeat(
                        $softTabs ? $this->SoftTab : $this->Tab,
                        $indent
                    );
                }
            }
        }
        if ($padding) {
            $before .= str_repeat(' ', $padding);
        }
        return $before;
    }

    private function tagHasAdjacentBracket(Token $token): bool
    {
        if ($token->OpenTag === $token) {
            return (
                $token->Next
                && $token->Next->OpenedBy
            ) || (
                $token->CloseTag
                && $token->CloseTag->Prev
                && $token->CloseTag->Prev->ClosedBy
            );
        }
        /** @var Token */
        $prev = $token->Prev;
        return $prev->ClosedBy || $prev->OpenedBy;
    }

    public function renderWhitespaceAfter(Token $token): string
    {
        if (
            $this->Idx->NotTrimmable[$token->id]
            || $this->Idx->LeftTrimmable[$token->id]
        ) {
            return '';
        }

        return TokenUtil::getWhitespace($token->effectiveWhitespaceAfter());
    }

    private function getIndentSpaces(Token $token): int
    {
        return (
            $token->TagIndent
            + $token->PreIndent
            + $token->Indent
            + $token->HangingIndent
            - $token->Deindent
        ) * $this->Formatter->TabSize + (
            $token->LinePadding
            - $token->LineUnpadding
        );
    }

    private function getIndentSpacesFromText(Token $token): int
    {
        if (!Regex::match('/^(?:\s*\n)?(?<indent>\h*)\S/', $token->text, $match)) {
            return 0;
        }

        return strlen(Str::expandTabs($match['indent'], $this->Formatter->TabSize));
    }

    private function setPosition(
        Token $token,
        string $text,
        bool $textMayHaveTabs
    ): void {
        $token->OutputPos += strlen($text);
        if ($textMayHaveTabs && strpos($text, "\t") !== false) {
            $text = Str::expandTabs(
                $text,
                $this->Formatter->TabSize,
                $token->OutputColumn
            );
        }
        $newlines = substr_count($text, "\n");
        if ($newlines) {
            $token->OutputLine += $newlines;
            $token->OutputColumn = mb_strlen($text) - mb_strrpos($text, "\n");
        } else {
            $token->OutputColumn += mb_strlen($text);
        }
    }

    private function getMultiLineComment(Token $token, bool $softTabs): string
    {
        // If the token is a C-style comment where at least one line starts with
        // a character other than "*", and neither delimiter appears on its own
        // line, reindent it to preserve alignment
        if (
            $token->id === \T_COMMENT
            && !($token->Flags & TokenFlag::INFORMAL_DOC_COMMENT)
        ) {
            $text = $token->expandedText();
            $delta = $token->OutputColumn - $token->column;
            /* Don't reindent if the comment hasn't moved, or if it has text in
column 1 despite starting in column 2 or above (like this comment) */
            if (!$delta || (
                $token->column > 1
                && Regex::match('/\n(?!\*)\S/', $text)
            )) {
                return $this->maybeUnexpandTabs($text, $softTabs);
            }
            $spaces = str_repeat(' ', abs($delta));
            if ($delta < 0) {
                // Don't deindent if any non-empty lines have insufficient
                // whitespace
                if (Regex::match("/\\n(?!{$spaces}|\h*+\\n)/", $text)) {
                    return $this->maybeUnexpandTabs($text, $softTabs);
                }
                return $this->maybeUnexpandTabs(
                    str_replace("\n" . $spaces, "\n", $text),
                    $softTabs,
                );
            }
            return $this->maybeUnexpandTabs(
                str_replace("\n", "\n" . $spaces, $text),
                $softTabs,
            );
        }

        if (!$token->Prev || ($start = $token->startOfLine()) === $token) {
            $tabs = $token->TagIndent
                + $token->PreIndent
                + $token->Indent
                + $token->HangingIndent
                - $token->Deindent;
            $spaces = $token->LinePadding
                - $token->LineUnpadding
                + $token->Padding;
            $indent = "\n"
                . ($tabs ? str_repeat($softTabs ? $this->SoftTab : $this->Tab, $tabs) : '')
                . ($spaces ? str_repeat(' ', $spaces) : '');
        } else {
            $beforeStart = $this->renderWhitespaceBefore($start, $softTabs);
            $indent = "\n" . ltrim($beforeStart, "\n")
                . str_repeat(' ', mb_strlen($this->render($start, $token->Prev, $softTabs))
                    - strlen($beforeStart)
                    + strlen(TokenUtil::getWhitespace($token->effectiveWhitespaceBefore()))
                    + $token->Padding);
        }
        $text = str_replace("\n", $indent, $token->text);

        return $text;
    }

    private function maybeUnexpandTabs(string $text, bool $softTabs): string
    {
        // Remove trailing whitespace
        $text = Regex::replace('/\h++$/m', '', $text);
        if ($this->Tab === "\t" && !$softTabs) {
            return Regex::replace("/(?<=\\n|\G){$this->SoftTab}/", "\t", $text);
        }
        return $text;
    }
}
