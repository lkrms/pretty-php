<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\BlockRuleTrait;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

/**
 * Align comments beside code
 *
 */
final class AlignComments implements BlockRule
{
    use BlockRuleTrait;

    /**
     * @var array<array{TokenCollection[],Token[]}>
     */
    private $BlockComments = [];

    public function getPriority(string $method): ?int
    {
        if ($method === self::BEFORE_RENDER) {
            return 998;
        }

        return 340;
    }

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }

        // Collect comments that appear beside code
        $comments = [];
        foreach ($block as $i => $line) {
            /** @var Token|null $lastComment */
            $lastComment = $prevComment ?? null;
            $prevComment = null;

            $comment = $line->getLastOf(...TokenType::COMMENT);
            if (!$comment || !$comment->hasNewlineAfter()) {
                continue;
            }

            if ($comment->hasNewlineBefore()) {
                /**
                 * A comment on its own line is considered standalone if it
                 * doesn't continue a comment on the preceding line:
                 *
                 * ```php
                 * $a = 1;
                 * $b = 2;  // Comment 1
                 *          // Comment 2 continues comment 1
                 * $c = 3;  // Comment 3
                 *
                 * // Comment 4 is a standalone comment
                 * ```
                 */
                $prev = $comment->prev();
                $standalone = $prev !== $lastComment ||
                    $comment->isMultiLineComment() ||
                    $lastComment->isMultiLineComment();

                if ($standalone || $comment->line - $prev->line > 1) {
                    /**
                     * Preserve blank lines so comments don't merge on
                     * subsequent runs:
                     *
                     * ```php
                     * $a = 1;
                     * $b = 2;  // Comment 1
                     *
                     * // If the blank line were removed, this would become part
                     * // of comment 1 on the next run
                     * $c = 3;
                     * ```
                     */
                    if (!$standalone) {
                        $comment->WhitespaceBefore |= WhitespaceType::BLANK;
                    }
                    continue;
                }
            }

            $prevComment = $comments[$i] = $comment;
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
                $token = $line[0];
                $comment = $comments[$i];
                // If $comment is the first token on the line, there won't be
                // anything to collect between $token and $comment->prev(), so use
                // $comment's leading whitespace for calculations
                if ($token === $comment) {
                    $length = strlen($comment->renderWhitespaceBefore(true));
                    $lengths[$i] = $length;
                    $max = max($max, $length);
                    continue;
                }
                $line = $token->collect($comment->prev());
                foreach (explode("\n", $line->render(true, false)) as $line) {
                    $length = mb_strlen(trim($line, "\r"));
                    $lengths[$i] = $length;
                    $max = max($max, $length);
                }
            }
            foreach ($comments as $i => $comment) {
                $comment->Padding = $max - $lengths[$i]
                    + ($comment->hasNewlineBefore()
                        ? ($tabWidth ?? ($tabWidth = strlen(WhitespaceType::toWhitespace(WhitespaceType::TAB))))
                        : 0);
            }
        }
    }

    public function reset(): void
    {
        $this->BlockComments = [];
    }
}
