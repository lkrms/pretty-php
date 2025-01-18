<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Internal;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Regex;

/**
 * @internal
 */
final class Declaration
{
    private const EXPANDABLE_TAG = '@(?:phan-|phpstan-|psalm-)?(?:'
        . 'api|internal|'
        . 'method|property(?:-read|-write)?|mixin|'
        . 'param(?:-[[:alnum:]]++)*|return|throws|'
        . 'template(?:-covariant|-contravariant)?|'
        . '(?:(?i)inheritDoc)'
        . ')(?=\s|$)';

    public Token $Token;
    public Token $End;
    public int $Type;
    /** @var int[] */
    public array $Modifiers;
    public ?Token $DocComment = null;
    private bool $HasMultiLineDocComment;
    private bool $HasBlankBefore;
    private bool $IsMultiLine;

    /**
     * @param int[] $modifiers
     */
    public function __construct(
        Token $token,
        int $type,
        array $modifiers
    ) {
        /** @var Token */
        $end = $token->EndStatement;

        $this->Token = $token;
        $this->End = $end;
        $this->Type = $type;
        $this->Modifiers = $modifiers;

        while ($token = $token->Prev) {
            if ($token->id === \T_DOC_COMMENT) {
                $this->DocComment = $token;
                break;
            }
            if ($token->id !== \T_COMMENT) {
                break;
            }
        }
    }

    /**
     * Check if this is a one-line declaration with a collapsed or collapsible
     * DocBlock (or no DocBlock at all)
     */
    public function isCollapsible(): bool
    {
        return !$this->hasMultiLineDocComment()
            && !$this->isMultiLine();
    }

    /**
     * Check if the declaration has a non-collapsible multi-line DocBlock
     */
    public function hasMultiLineDocComment(): bool
    {
        return $this->HasMultiLineDocComment ??=
            ($comment = $this->DocComment) && !(
                (
                    $comment->Flags & Flag::COLLAPSIBLE_COMMENT
                    && ($comment->Data[Data::COMMENT_CONTENT][0] ?? null) === '@'
                    && !Regex::match(
                        '/^' . self::EXPANDABLE_TAG . '/',
                        $comment->Data[Data::COMMENT_CONTENT],
                    )
                ) || (
                    // Check if the comment is already collapsed
                    !$comment->hasNewline()
                    && $comment->text[4] === '@'
                    && !Regex::match(
                        '/^\/\*\* ' . self::EXPANDABLE_TAG . '/',
                        $comment->text,
                    )
                )
            );
    }

    /**
     * Check if there is a blank line before the declaration's DocBlock, first
     * pinned comment, or first token (whichever is applicable)
     */
    public function hasBlankBefore(): bool
    {
        return $this->HasBlankBefore ??=
            ($this->DocComment ?? $this->Token->skipToFirstPinnedComment())
                ->hasBlankBefore();
    }

    /**
     * Check if the declaration's code breaks over multiple lines
     */
    public function isMultiLine(): bool
    {
        return $this->IsMultiLine ??=
            $this->doIsMultiLine();
    }

    private function doIsMultiLine(): bool
    {
        $token = $this->Token;
        /** @var Token */
        $end = $this->Type === Type::HOOK
            ? $token->nextSiblingFrom($token->Idx->StartOfPropertyHookBody)
                    ->PrevCode
            : $this->End;

        return $token->collect($end)->hasNewline();
    }

    /**
     * Add a blank line before the declaration
     */
    public function applyBlankBefore(bool $force = false): void
    {
        unset($this->HasBlankBefore);

        if ($comment = $this->DocComment) {
            $comment->Whitespace |= Space::BLANK_BEFORE;
            if ($force) {
                $comment->removeWhitespace(Space::NO_BLANK_BEFORE);
            }
            // Suppress blank lines between the DocBlock and the declaration
            if ($comment !== $this->Token->Prev) {
                $comment->collect($this->Token)
                        ->setInnerWhitespace(Space::NO_BLANK);
            }
        } else {
            $this->Token->applyBlankBefore($force);
        }
    }

    /**
     * Add a blank line after the declaration
     */
    public function applyBlankAfter(): void
    {
        if (
            ($next = $this->End->Next)
            && $next->id !== \T_CLOSE_TAG
        ) {
            $this->End->Whitespace |= Space::BLANK_AFTER;
        }
    }
}
