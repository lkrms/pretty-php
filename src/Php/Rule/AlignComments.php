<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\BlockRuleTrait;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\Php\TokenCollection;

class AlignComments implements BlockRule
{
    use BlockRuleTrait;

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        // Collect comments that appear beside code, but don't calculate line
        // lengths until we know the rendering expense is necessary
        $comments = [];
        $first    = null;
        $last     = null;
        foreach ($block as $i => $line) {
            if (($comment = $line->getLastOf(...TokenType::COMMENT)) &&
                    $comment->hasNewlineAfter() &&
                    !$comment->hasNewlineBefore() &&
                    !$comment->hasNewline()) {
                $comments[$i] = $comment;
                /** @var Token $first */
                $first        = $first ?: $line[0];
                /** @var Token $last */
                $last         = $line[0];
            }
        }
        if (count($comments) < 2) {
            return;
        }
        $this->Formatter->registerCallback($this, reset($comments), fn() =>
            $this->alignComments($block, $comments, $first, $last), 999);
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
            // Ignore lines before $first and after $last unless their
            // bracket stacks match $first and $last respectively
            if (!$lengths && $token->BracketStack !== $first->BracketStack ||
                    ($token->Index > $last->Index && $token->BracketStack !== $last->BracketStack)) {
                continue;
            }
            if ($comment = $comments[$i] ?? null) {
                $line = $token->collect($comment->prev());
            }
            $length      = strlen($line->render());
            $lengths[$i] = $length;
            $max         = max($max, $length);
        }
        /** @var Token $comment */
        foreach ($comments as $i => $comment) {
            $comment->Padding += $max - $lengths[$i];
        }
    }
}
