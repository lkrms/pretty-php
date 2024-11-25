<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Place comments beside code, above code, or inside code
 *
 * @api
 */
final class PlaceComments implements TokenRule
{
    use TokenRuleTrait;

    /** @var Token[] */
    private array $CommentsBesideCode = [];

    /**
     * [ [ Comment token, subsequent code token ] ]
     *
     * @var array<Token[]>
     */
    private array $Comments = [];

    /** @var Token[] */
    private array $CollapsibleComments = [];

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 90,
            self::BEFORE_RENDER => 997,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return $idx->Comment;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->isOneLineComment()
                && $token->Next
                && $token->Next->id !== \T_CLOSE_TAG
            ) {
                $token->Whitespace |= Space::CRITICAL_LINE_AFTER;
            }

            $isDocComment =
                $token->id === \T_DOC_COMMENT
                || ($token->Flags & TokenFlag::INFORMAL_DOC_COMMENT);

            $prev = $token->Prev;
            $prevIsTopLevelCloseBrace =
                $prev
                && $prev->id === \T_CLOSE_BRACE
                && $prev->Flags & TokenFlag::STRUCTURAL_BRACE
                && $prev->skipPrevSiblingsToDeclarationStart()
                        ->namedDeclarationParts()
                        ->hasOneFrom($this->Idx->DeclarationClass);

            $needsNewlineBefore =
                $token->id === \T_DOC_COMMENT
                || ($this->Formatter->Psr12 && $prevIsTopLevelCloseBrace);

            if (!$needsNewlineBefore) {
                // Leave embedded comments alone
                $wasFirstOnLine = $token->wasFirstOnLine();
                if (!$wasFirstOnLine && !$token->wasLastOnLine()) {
                    if ($prev && (
                        $prev->Flags & TokenFlag::CODE
                        || $prev->OpenTag === $prev
                    )) {
                        $this->CommentsBesideCode[] = $token;
                        $token->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
                        continue;
                    }
                    $token->Whitespace |= Space::SPACE_BEFORE | Space::SPACE_AFTER;
                    continue;
                }

                // Aside from DocBlocks and, in strict PSR-12 mode, comments after
                // top-level close braces, don't move comments to the next line
                if (!$wasFirstOnLine) {
                    $token->Whitespace |= Space::LINE_AFTER | Space::SPACE_AFTER;
                    if ($prev && (
                        $prev->Flags & TokenFlag::CODE
                        || $prev->OpenTag === $prev
                    )) {
                        $this->CommentsBesideCode[] = $token;
                        $token->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
                        continue;
                    }
                    $token->Whitespace |= Space::SPACE_BEFORE;
                    continue;
                }
            }

            // Copy indentation and padding from `$next` to `$token` in
            // `beforeRender()` unless `$next` is a close bracket
            $next = $token->NextCode;
            if ($next && !$this->Idx->CloseBracketOrAlt[$next->id]) {
                $this->Comments[] = [$token, $next];
            }

            $token->Whitespace |= Space::LINE_BEFORE | Space::SPACE_BEFORE | Space::LINE_AFTER;

            if (!$isDocComment) {
                continue;
            }

            // Add a blank line before multi-line DocBlocks and C-style equivalents
            // unless they appear mid-statement
            if ($token->Flags & TokenFlag::COLLAPSIBLE_COMMENT) {
                $this->CollapsibleComments[] = $token;
            } elseif (
                $token->hasNewline()
                && (!$token->PrevSibling
                    || !$token->NextSibling
                    || $token->PrevSibling->Statement !== $token->NextSibling->Statement)
            ) {
                $token->Whitespace |= Space::BLANK_BEFORE;
            }

            // Add a blank line after file-level DocBlocks and multi-line C-style
            // comments
            if (
                $next && (
                    $next->id === \T_DECLARE
                    || $next->id === \T_NAMESPACE
                    || (
                        $next->id === \T_USE
                        && $next->getSubType() === TokenSubType::USE_IMPORT
                    )
                )
            ) {
                $token->Whitespace |= Space::BLANK_AFTER;
                continue;
            }

            // Otherwise, pin DocBlocks to subsequent code
            if (
                $next
                && $next === $token->Next
                && $token->id === \T_DOC_COMMENT
            ) {
                $token->Whitespace |= Space::NO_BLANK_AFTER;
            }
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->CommentsBesideCode as $token) {
            if (!$token->hasNewlineBefore()) {
                $token->Whitespace |= Space::SPACE_BEFORE;
                if ($token->hasNewlineAfter()) {
                    $token->removeWhitespace(Space::NO_SPACE_BEFORE);
                    $token->Padding = $this->Formatter->SpacesBesideCode - 1;
                } else {
                    $token->Whitespace |= Space::SPACE_AFTER;
                }
            }
        }

        foreach ($this->Comments as [$token, $next]) {
            // Comments are usually aligned to the code below them, but `switch`
            // constructs are a special case, e.g.:
            //
            // ```php
            // switch ($a) {
            //     //
            //     case 0:
            //     case 1:
            //         //
            //         func();
            //         // Indented
            //     case 2:
            //         // Indented
            //     case 3:
            //         func2();
            //         break;
            //
            //         // Indented
            //
            //     case 4:
            //         func2();
            //         break;
            //
            //         // Indented
            //
            //     //
            //     case 5:
            //         func();
            //         break;
            //
            //     //
            //     default:
            //         break;
            // }
            // ```
            //
            // This is accommodated by adding a level of indentation to comments
            // before `case`/`default` unless they appear after the opening
            // brace or between a blank line and the next `case`/`default`.
            //
            $indent = 0;
            if (
                ($next->id === \T_CASE || $next->id === \T_DEFAULT)
                && $next->Parent
                && $next->Parent->PrevSibling
                && $next->Parent->PrevSibling->PrevSibling
                && $next->Parent->PrevSibling->PrevSibling->id === \T_SWITCH
            ) {
                $prev = $token->PrevCode;
                if (!($token->Parent === $prev
                    || ($prev->collect($token)->hasBlankLineBetweenTokens()
                        && !$token->collect($next)->hasBlankLineBetweenTokens()))) {
                    $indent = 1;
                }
            }

            [
                $token->PreIndent,
                $token->Indent,
                $token->Deindent,
                $token->HangingIndent,
                $token->LinePadding,
                $token->LineUnpadding
            ] = [
                $next->PreIndent,
                $next->Indent + $indent,
                $next->Deindent,
                $next->HangingIndent,
                $next->LinePadding,
                $next->LineUnpadding
            ];

            if ($token->hasNewlineAfter()) {
                $token->Padding = $next->Padding;
            }
        }

        foreach ($this->CollapsibleComments as $token) {
            if ($token->hasNewline()) {
                $token->Whitespace |= Space::BLANK_BEFORE;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->CommentsBesideCode = [];
        $this->Comments = [];
        $this->CollapsibleComments = [];
    }
}
