<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

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
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 90;

            case self::BEFORE_RENDER:
                return 997;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return TokenType::COMMENT;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->isOneLineComment()
                && $token->Next
                && $token->Next->id !== \T_CLOSE_TAG
            ) {
                $token->CriticalWhitespaceAfter |= WhitespaceType::LINE;
            }

            $isDocComment =
                $token->id === \T_DOC_COMMENT
                || ($token->Flags & TokenFlag::INFORMAL_DOC_COMMENT);

            $prevIsTopLevelCloseBrace =
                $token->Prev
                && $token->Prev->id === \T_CLOSE_BRACE
                && $token->Prev->isStructuralBrace()
                && $token->Prev->skipPrevSiblingsToDeclarationStart()->namedDeclarationParts()->hasOneOf(
                    ...TokenType::DECLARATION_CLASS
                );

            $needsNewlineBefore =
                $token->id === \T_DOC_COMMENT
                || ($this->Formatter->Psr12 && $prevIsTopLevelCloseBrace);

            if (!$needsNewlineBefore) {
                // Leave embedded comments alone
                $wasFirstOnLine = $token->wasFirstOnLine();
                if (!$wasFirstOnLine && !$token->wasLastOnLine()) {
                    if ($token->Prev->IsCode || $token->Prev->OpenTag === $token->Prev) {
                        $this->CommentsBesideCode[] = $token;
                        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                        continue;
                    }
                    $token->WhitespaceBefore |= WhitespaceType::SPACE;
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    continue;
                }

                // Aside from DocBlocks and, in strict PSR-12 mode, comments after
                // top-level close braces, don't move comments to the next line
                if (!$wasFirstOnLine) {
                    $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
                    if ($token->Prev->IsCode || $token->Prev->OpenTag === $token->Prev) {
                        $this->CommentsBesideCode[] = $token;
                        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                        continue;
                    }
                    $token->WhitespaceBefore |= WhitespaceType::SPACE;
                    continue;
                }
            }

            // Copy indentation and padding from `$next` to `$token` in
            // `beforeRender()` unless `$next` is a close bracket
            $next = $token->NextCode;
            if ($next && !$this->Idx->CloseBracketOrEndAltSyntax[$next->id]) {
                $this->Comments[] = [$token, $next];
            }

            $token->WhitespaceAfter |= WhitespaceType::LINE;
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;

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
                $token->WhitespaceBefore |= WhitespaceType::BLANK;
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
                $token->WhitespaceAfter |= WhitespaceType::BLANK;
                continue;
            }

            // Otherwise, pin DocBlocks to subsequent code
            if (
                $next
                && $next === $token->Next
                && $token->id === \T_DOC_COMMENT
            ) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            }
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->CommentsBesideCode as $token) {
            if (!$token->hasNewlineBefore()) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                if ($token->hasNewlineAfter()) {
                    $token->Prev->WhitespaceMaskNext |= WhitespaceType::SPACE;
                    $token->Padding = $this->Formatter->SpacesBesideCode - 1;
                } else {
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
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
                $token->WhitespaceBefore |= WhitespaceType::BLANK;
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
