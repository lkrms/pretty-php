<?php
fn()
    => $foo
    || $bar;  // Remove indentation here

$foo = bar(
    'foo',
    'bar'
)  // Add indentation here
    ->baz()
    ->qux();

if (
    $comments
    && ($prev = end($comments)) === $comment->Prev
    && $comment->line - $prev->line < 2
    && ($comment->Flags & TokenFlagMask::COMMENT_TYPE)
        === ($prev->Flags & TokenFlagMask::COMMENT_TYPE)
    // Remove indentation here
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
