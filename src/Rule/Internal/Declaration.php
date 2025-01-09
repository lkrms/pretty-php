<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Internal;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
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
    private bool $HasDocComment;
    private bool $HasDocCommentOrBlankBefore;
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
    }

    /**
     * Check if this is a one-line declaration with a collapsed or collapsible
     * DocBlock (or no DocBlock at all)
     */
    public function isCollapsible(): bool
    {
        return !$this->hasDocComment()
            && !$this->isMultiLine();
    }

    /**
     * Check if the declaration has a non-collapsible multi-line DocBlock
     */
    public function hasDocComment(bool $orBlankBefore = false): bool
    {
        if ($orBlankBefore) {
            return $this->HasDocCommentOrBlankBefore ??=
                ($this->HasDocComment ?? false)
                || $this->doHasDocComment(true);
        }
        return $this->HasDocComment ??=
            $this->doHasDocComment(false);
    }

    private function doHasDocComment(bool $orBlankBefore): bool
    {
        $token = $this->Token;
        /** @var Token */
        $prev = $token->Prev;

        if ($prev->id !== \T_DOC_COMMENT) {
            return $orBlankBefore && $token->hasBlankBefore();
        }

        if (!$prev->hasNewlineBefore() || $prev->hasBlankAfter()) {
            return false;
        }

        return !(
            (
                $prev->Flags & TokenFlag::COLLAPSIBLE_COMMENT
                && ($prev->Data[TokenData::COMMENT_CONTENT][0] ?? null) === '@'
                && !Regex::match(
                    '/^' . self::EXPANDABLE_TAG . '/',
                    $prev->Data[TokenData::COMMENT_CONTENT],
                )
            ) || (
                // Check if the comment is already collapsed
                !$prev->hasNewline()
                && $prev->text[4] === '@'
                && !Regex::match(
                    '/^\/\*\* ' . self::EXPANDABLE_TAG . '/',
                    $prev->text,
                )
            )
        ) || (
            $orBlankBefore && $prev->hasBlankBefore()
        );
    }

    /**
     * Check if the declaration breaks over multiple lines
     */
    public function isMultiLine(): bool
    {
        return $this->IsMultiLine ??=
            $this->doIsMultiLine();
    }

    private function doIsMultiLine(): bool
    {
        $token = $this->Token;
        $idx = $token->Idx;
        /** @var Token */
        $end = $this->Type === Type::HOOK
            ? $token->nextSiblingFrom($idx->StartOfPropertyHookBody)->PrevCode
            : $this->End;

        return $token->collect($end)->hasNewline();
    }

    /**
     * Add a blank line before the declaration
     */
    public function applyBlankBefore(bool $force = false): void
    {
        unset(
            $this->HasDocComment,
            $this->HasDocCommentOrBlankBefore,
        );

        $this->Token->applyBlankBefore($force);
    }
}
