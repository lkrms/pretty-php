<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\BlockRuleTrait;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

class AlignComments implements BlockRule
{
    use BlockRuleTrait;

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        // Collect comments that appear beside code
        $comments             = [];
        $firstLineWithComment = null;
        $lastLineWithComment  = null;
        foreach ($block as $i => $line) {
            /** @var Token|null $lastComment */
            $lastComment = $prevComment ?? null;
            $prevComment = null;
            $comment     = $line->getLastOf(...TokenType::COMMENT);
            if (!$comment || !$comment->hasNewlineAfter()) {
                continue;
            }
            if ($comment->hasNewlineBefore()) {
                $prev = $comment->prev();
                $standalone = $prev !== $lastComment ||
                    $comment->isMultiLineComment() ||
                    $lastComment->isMultiLineComment();
                if ($standalone || $comment->Line - $prev->Line > 1) {
                    /**
                     * Preserve blank lines so comments don't merge on
                     * subsequent runs:
                     *
                     * ```php
                     * $a = 1;
                     * $b = 2;    // Comment
                     *
                     * // If the blank line were removed, this would become part
                     * // of the comment beside `$b = 2;` on the next run
                     * $c = 3;
                     * ```
                     */
                    if (!$standalone) {
                        $comment->WhitespaceBefore |= WhitespaceType::BLANK;
                    }
                    continue;
                }
            }

            $comments[$i]         = $comment;
            /** @var Token $firstLineWithComment */
            $firstLineWithComment = $firstLineWithComment ?: $line[0];
            /** @var Token $lastLineWithComment */
            $lastLineWithComment  = $line[0];
            $prevComment          = $comment;
        }
        if (count($comments) < 2) {
            return;
        }
        $this->Formatter->registerCallback($this, reset($comments), fn() =>
            $this->alignComments($block, $comments, $firstLineWithComment, $lastLineWithComment), 999);
    }

    /**
     * @param TokenCollection[] $block
     * @param Token[] $comments
     */
    private function alignComments(array $block, array $comments, Token $first, Token $last): void
    {
        $lengths = [];
        $max     = 0;
        foreach ($block as $i => $line) {
            /** @var Token $token */
            $token = $line[0];
            // Ignore lines before $first and after $last unless their bracket
            // stacks match $first and $last respectively
            if (!$lengths && $token->BracketStack !== $first->BracketStack ||
                    ($token->Index > $last->Index && $token->BracketStack !== $last->BracketStack)) {
                continue;
            }
            if ($comment = $comments[$i] ?? null) {
                // If $comment is the first token on the line, there won't be
                // anything to collect between $token and $comment->prev(), so
                // use $comment's leading whitespace instead
                if ($token === $comment) {
                    $length      = strlen(ltrim($comment->renderWhitespaceBefore(true), "\n"));
                    $lengths[$i] = $length;
                    $max         = max($max, $length);
                    continue;
                }
                $line = $token->collect($comment->prev());
            } elseif ($token->isOneOf(...TokenType::COMMENT)) {
                continue;
            }
            foreach (explode("\n", ltrim($line->render(true, false), "\n")) as $line) {
                $length      = mb_strlen(trim($line, "\r"));
                $lengths[$i] = $length;
                $max         = max($max, $length);
            }
        }
        /** @var Token $comment */
        foreach ($comments as $i => $comment) {
            $comment->Padding = $max - $lengths[$i]
                + ($comment->hasNewlineBefore()
                    ? ($tabWidth ?? ($tabWidth = strlen(WhitespaceType::toWhitespace(WhitespaceType::TAB))))
                    : 0);
        }
    }
}
