<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Generator;

/**
 * @api
 */
final class Renderer implements Immutable
{
    use HasMutator;

    private int $TabSize;
    private string $SoftTab;
    private string $Tab;
    private AbstractTokenIndex $Idx;

    public function __construct(Formatter $formatter)
    {
        $this->TabSize = $formatter->TabSize;
        $this->SoftTab = $formatter->SoftTab;
        $this->Tab = $formatter->Tab;
        $this->Idx = $formatter->TokenIndex;
    }

    /**
     * Get an instance with the given formatter
     *
     * @return static
     */
    public function withFormatter(Formatter $formatter): self
    {
        return $this->with('TabSize', $formatter->TabSize)
                    ->with('SoftTab', $formatter->SoftTab)
                    ->with('Tab', $formatter->Tab)
                    ->with('Idx', $formatter->TokenIndex);
    }

    /**
     * @param bool $final Reindent multi-line comments and set the output
     * position properties of each token?
     */
    public function render(
        Token $from,
        Token $to,
        bool $softTabs = false,
        bool $final = false,
        bool $noWhitespaceBefore = false,
        bool $noWhitespaceAfter = false
    ): string {
        $indentMayHaveTabs = !$softTabs && $this->Tab === "\t";

        $t = $from;
        $code = '';
        do {
            if ($this->Idx->Virtual[$t->id]) {
                $after = $before = '';
            } else {
                // Render whitespace before the token
                $before = $noWhitespaceBefore
                    && $t === $from
                        ? ''
                        : $this->renderWhitespaceBefore($t, $softTabs);

                // If whitespace after the token will not be rendered on the
                // next iteration, render it now
                $isLast = $t === $to
                    || !$t->Next
                    || (
                        $this->Idx->Virtual[$t->Next->id]
                        && !$t->Next->Data[Data::NEXT_REAL]
                    );
                $after = (
                    $noWhitespaceAfter
                    && $isLast
                ) || !(
                    $isLast
                    || !$t->Next
                    || $this->Idx->NotLeftTrimmable[$t->Next->id]
                )
                    ? ''
                    : $this->renderWhitespaceAfter($t);
            }

            if ($final) {
                if ($t === $from) {
                    $t->OutputLine = 1;
                    $t->OutputColumn = 1;
                    $t->OutputPos = 0;
                }

                // Adjust the token's position for any leading whitespace
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
            ) && ($heredocIndent = $this->getHeredocIndent($t)) !== '') {
                // Remove redundant whitespace from empty heredoc lines
                $text = Regex::replace(
                    ($t->Next->text[0] ?? null) === "\n"
                        ? "/\\n{$heredocIndent}\$/m"
                        : "/\\n{$heredocIndent}(?=\\n)/",
                    "\n",
                    $t->text,
                );
            } else {
                $text = $t->text;
            }

            $output = $text . $after;
            if ($final && $t->Next && $output !== '') {
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
        if ($this->Idx->NotLeftTrimmable[$token->id]) {
            return '';
        }

        $before = '';
        $padding = $token->Padding;
        if ($whitespace = $token->getWhitespaceBefore()) {
            $before = TokenUtil::getWhitespace($whitespace);
            // i.e. if LINE or BLANK are set
            if ($whitespace > 1) {
                $indent = $token->TagIndent
                    + $token->PreIndent
                    + $token->Indent
                    + $token->HangingIndent
                    - $token->Deindent;
                $padding += $token->LinePadding
                    - $token->LineUnpadding;

                // If this is a close tag with subsequent text not indented by
                // at least the same amount, perform some sanity checks
                if (
                    $token->id === \T_CLOSE_TAG
                    && $token->Next
                    && $this->getIndentSpacesFromText($token->Next)
                        < $indent * $this->TabSize + $padding
                ) {
                    // Copy the open tag's indentation if it's at the same depth
                    /** @var Token */
                    $openTag = $token->OpenTag;
                    if (
                        $token->Depth === $openTag->Depth
                        && $openTag->TagIndent !== null
                    ) {
                        $indent = $openTag->TagIndent;
                        $padding = 0;
                    } else {
                        // Otherwise, if the document has no indented open tags,
                        // don't indent the close tag
                        $found = false;
                        /** @var Token $tag */
                        foreach ($this->getOpenTags($token) as $tag) {
                            if (!$tag->TagIndent) {
                                continue;
                            }
                            // Ignore one-line blocks
                            $end = $tag->CloseTag ?? $tag->last();
                            /** @var Token */
                            $end = $end->Prev;
                            if (!$tag->collect($end)->hasNewline()) {
                                continue;
                            }
                            $found = true;
                            break;
                        }
                        if (!$found) {
                            $indent = 0;
                            $padding = 0;
                        }
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

        return $padding
            ? $before . str_repeat(' ', $padding)
            : $before;
    }

    public function renderWhitespaceAfter(Token $token): string
    {
        return $this->Idx->NotRightTrimmable[$token->id]
            || !($whitespace = $token->getWhitespaceAfter())
                ? ''
                : TokenUtil::getWhitespace($whitespace);
    }

    /**
     * @return Generator<Token>
     */
    private function getOpenTags(Token $token): Generator
    {
        $sources = [
            $this->getNextOpenTags($token),
            $this->getPrevOpenTags($token),
        ];

        do {
            foreach ($sources as $i => $source) {
                if ($source->valid()) {
                    yield $source->current();
                    $source->next();
                } else {
                    unset($sources[$i]);
                }
            }
        } while ($sources);
    }

    /**
     * @return Generator<Token>
     */
    private function getNextOpenTags(Token $token): Generator
    {
        if ($token->CloseTag) {
            $token = $token->CloseTag;
        }
        while ($token->Next) {
            $token = $token->Next;
            if ($token === $token->OpenTag) {
                yield $token;
                if ($token->CloseTag) {
                    $token = $token->CloseTag;
                    continue;
                }
                break;
            }
        }
    }

    /**
     * @return Generator<Token>
     */
    private function getPrevOpenTags(Token $token): Generator
    {
        while ($token->Prev) {
            $token = $token->Prev;
            if ($token->OpenTag) {
                $token = $token->OpenTag;
                yield $token;
            }
        }
    }

    private function getIndentSpacesFromText(Token $token): int
    {
        if (!Regex::match('/^(?:\s*\n)?(?<indent>\h*)\S/', $token->text, $match)) {
            return 0;
        }

        return strlen(Str::expandTabs($match['indent'], $this->TabSize));
    }

    private function getMultiLineComment(Token $token, bool $softTabs): string
    {
        // If the comment hasn't been normalised, reindent it to preserve
        // alignment if possible
        if (
            $token->id === \T_COMMENT
            && !($token->Flags & Flag::C_DOC_COMMENT)
        ) {
            $text = $token->expandText(true);
            $delta = $token->OutputColumn - $token->column;
            // Don't reindent if the comment hasn't moved, or if it has text
            // other than asterisks in column 1 despite starting later
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

        // If the comment has been normalised, replace newlines with indentation
        // derived from its location on the line
        if (($start = $token->startOfLine()) === $token) {
            $tabs = $token->TagIndent
                + $token->PreIndent
                + $token->Indent
                + $token->HangingIndent
                - $token->Deindent;
            $spaces = $token->Padding
                + $token->LinePadding
                - $token->LineUnpadding;
            $indent = "\n"
                . ($tabs ? str_repeat($softTabs ? $this->SoftTab : $this->Tab, $tabs) : '')
                . ($spaces ? str_repeat(' ', $spaces) : '');
        } else {
            /** @var Token */
            $prev = $token->Prev;
            $code = $this->render($start, $prev, $softTabs, false, false, true);
            $whitespaceLength = strspn($code, " \t\n");
            $whitespace = substr($code, 0, $whitespaceLength);
            $whitespaceBefore = ($before = $token->getWhitespaceBefore())
                ? TokenUtil::getWhitespace($before)
                : '';
            $spaces = mb_strlen($code)
                + strlen($whitespaceBefore)
                + $token->Padding
                - $whitespaceLength;
            $indent = "\n"
                . ltrim($whitespace, "\n")
                . str_repeat(' ', $spaces);
        }

        return str_replace("\n", $indent, $token->text);
    }

    private function getHeredocIndent(Token $token): string
    {
        $indent = '';
        /** @var Token */
        $t = $token->id === \T_START_HEREDOC
            ? $token
            : $token->Heredoc;
        do {
            $indent .= $t->HeredocIndent;
        } while ($t = $t->Heredoc);

        return $indent;
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

    private function setPosition(
        Token $token,
        string $text,
        bool $textMayHaveTabs
    ): void {
        $token->OutputPos += strlen($text);
        if ($textMayHaveTabs && strpos($text, "\t") !== false) {
            $text = Str::expandTabs(
                $text,
                $this->TabSize,
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
}
