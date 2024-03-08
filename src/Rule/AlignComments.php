<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Rule\Concern\BlockRuleTrait;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align comments beside code
 *
 * @api
 */
final class AlignComments implements BlockRule
{
    use BlockRuleTrait;

    /**
     * @var array<array{TokenCollection[],Token[]}>
     */
    private array $BlockComments = [];

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_BLOCK:
                return 340;

            case self::BEFORE_RENDER:
                return 998;

            default:
                return null;
        }
    }

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }

        // Collect comments that appear beside code
        $comments = [];
        /** @var Token|null */
        $lastStartOfLine = null;
        foreach ($block as $i => $line) {
            /** @var Token|null */
            $lastComment = $prevComment ?? null;
            $prevComment = null;

            $comment = $line->last();
            if (!$comment->CommentType) {
                continue;
            }

            if (!$comment->hasNewlineBefore()) {
                $comments[$i] = $prevComment = $comment;
                $lastStartOfLine = $line->first();
                continue;
            }

            /**
             * A comment on its own line is considered standalone unless it
             * continues a comment from the line before by having the same
             * one-line comment type (`//` or `#`) and being indented relative
             * to code in the same context
             *
             * ```php
             * $a = 1;
             * $b = 2;  // Comment 1
             *  // Continuation of comment 1 (indented relative to $b)
             * $c = 3;  // Comment 2
             * // Comment 3 (not indented relative to $c)
             * ```
             *
             * ```php
             * foreach ($array as $key => $value)  // Comment 1
             *     // Comment 2
             *     echo "$key: $value\n";
             * ```
             */
            $prev = $comment->Prev;
            if ($prev !== $lastComment ||
                $comment->line - $prev->line > 1 ||
                $comment->CommentType !== $prev->CommentType ||
                $comment->isMultiLineComment() ||
                $comment->column === 1 ||
                !$lastStartOfLine ||
                /** @todo Guess input tab size and use it instead */
                $comment->column <= $lastStartOfLine->column + (
                    $comment->Depth - $lastStartOfLine->Depth
                ) * $this->Formatter->TabSize ||
                ($comment->NextCode &&
                    $comment->NextCode->wasFirstOnLine() &&
                    $comment->column <= $comment->NextCode->column)) {
                continue;
            }

            $comments[$i] = $prevComment = $comment;
        }

        if (count($comments) < 2) {
            return;
        }

        $block = array_intersect_key($block, $comments);

        $this->BlockComments[] = [$block, $comments];
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->BlockComments as [$block, $comments]) {
            $lengths = [];
            $max = 0;
            foreach ($block as $i => $line) {
                /** @var Token */
                $token = $line[0];
                /** @var Token */
                $comment = $comments[$i];
                // If $comment is the first token on the line, there won't be
                // anything to collect between $token and $comment->Prev, so use
                // $comment's leading whitespace for calculations
                if ($token === $comment) {
                    $length = strlen($comment->renderWhitespaceBefore(true));
                    // Compensate for lack of SPACE applied by PlaceComments
                    $lengths[$i] = $length - 1;
                    $max = max($max, $length);
                    continue;
                }
                $text = $token->collect($comment->Prev)->render(true, false);
                $length = mb_strlen(mb_substr($text, mb_strrpos("\n" . $text, "\n")));
                $lengths[$i] = $length;
                $max = max($max, $length);
            }
            foreach ($comments as $i => $comment) {
                $comment->Padding = $max - $lengths[$i]
                    + $this->Formatter->SpacesBesideCode
                    // Compensate for SPACE applied by PlaceComments
                    - 1;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->BlockComments = [];
    }
}
