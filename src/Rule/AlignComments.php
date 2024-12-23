<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenFlagMask;
use Lkrms\PrettyPHP\Concern\BlockRuleTrait;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Token;

/**
 * Align comments beside code
 *
 * @api
 */
final class AlignComments implements BlockRule
{
    use BlockRuleTrait;

    /** @var array<non-empty-array<Token>> */
    private array $Comments;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_BLOCK => 340,
            self::BEFORE_RENDER => 998,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Comments = [];
    }

    /**
     * Apply the rule to the given code block
     *
     * Comments beside code, along with any continuations on subsequent lines,
     * are saved for alignment.
     *
     * C++- and shell-style comments on their own line after a comment beside
     * code are treated as continuations of the initial comment if they are of
     * the same type and were indented by at least one column relative to code
     * in the same context.
     */
    public function processBlock(array $lines): void
    {
        if (count($lines) < 2) {
            return;
        }

        $tabSize = $this->Formatter->Indentation->TabSize
            ?? $this->Formatter->TabSize;

        /** @var Token[] */
        $comments = [];
        $column = 1;
        $depth = 0;
        $nextCode = null;
        $nextCodeWasFirst = null;
        foreach ($lines as $tokens) {
            /** @var Token */
            $comment = $tokens->last();
            if (!$this->Idx->Comment[$comment->id]) {
                continue;
            }

            /** @var Token */
            $first = $tokens->first();
            if ($first !== $comment) {
                $comments[] = $comment;
                $column = $first->column;
                $depth = $first->Depth;
                $nextCode = $comment->NextCode;
                $nextCodeWasFirst = null;
            } elseif (
                $comments
                && ($prev = end($comments)) === $comment->Prev
                && $comment->line - $prev->line < 2
                && ($comment->Flags & TokenFlagMask::COMMENT_TYPE) === ($prev->Flags & TokenFlagMask::COMMENT_TYPE)
                && $comment->Flags & TokenFlag::ONELINE_COMMENT
                && $comment->column > 1
                && $comment->column > $column + ($comment->Depth - $depth) * $tabSize
                && (
                    !$nextCode
                    || !($nextCodeWasFirst ??= $nextCode->wasFirstOnLine())
                    || $comment->column > $nextCode->column
                )
            ) {
                $comments[] = $comment;
            }
        }

        if (count($comments) > 1) {
            $this->Comments[] = $comments;
        }
    }

    /**
     * Apply the rule to the given tokens
     *
     * Comments saved for alignment are aligned with the rightmost comment in
     * the block.
     */
    public function beforeRender(array $tokens): void
    {
        foreach ($this->Comments as $comments) {
            $count = 0;
            $max = 0;
            foreach ($comments as $i => $comment) {
                $columns[$i] = $column = $comment->getOutputColumn(true);
                $max = $count++
                    ? max($max, $column)
                    : $column;
            }
            foreach ($comments as $i => $comment) {
                $comment->Padding += $max - $columns[$i];
            }
        }
    }
}
