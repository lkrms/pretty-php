<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Place comments above or adjacent to code
 *
 * @api
 */
final class PlaceComments implements TokenRule
{
    use TokenRuleTrait;

    /** @var array<array{Token,Token}> */
    private array $Comments;
    /** @var Token[] */
    private array $CommentsBesideCode;
    /** @var Token[] */
    private array $CollapsibleComments;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 126,
            self::BEFORE_RENDER => 997,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->Comment;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->Comments = [];
        $this->CommentsBesideCode = [];
        $this->CollapsibleComments = [];
    }

    /**
     * Apply the rule to the given tokens
     *
     * Critical newlines are added after one-line comments followed by any token
     * other than a close tag.
     *
     * Newlines are added before and after:
     *
     * - DocBlocks
     * - comments with a leading newline in the input
     * - comments after top-level close braces (if strict PSR-12 mode is
     *   enabled)
     *
     * These comments are also saved for alignment with the next code token
     * (unless it's a close bracket).
     *
     * Leading and trailing spaces are added to comments that don't appear on
     * their own line, and comments where the previous token is a code token are
     * saved to receive padding derived from the value of the formatter's
     * `SpacesBesideCode` property if they are the last token on the line after
     * other rules are applied.
     *
     * For multi-line DocBlocks, and C-style comments that receive the same
     * treatment:
     *
     * - leading blank lines are added unless the comment appears mid-statement
     *   (deferred until after `DeclarationSpacing` for collapsible DocBlocks)
     * - trailing blank lines are added to file-level comments
     * - trailing blank lines are suppressed for DocBlocks with subsequent code
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->Flags & Flag::ONELINE_COMMENT
                && ($next = $token->nextReal())
                && $next->id !== \T_CLOSE_TAG
            ) {
                $token->Whitespace |= Space::CRITICAL_LINE_AFTER;
            }

            $prev = $token->Prev;
            if (
                $token->id !== \T_DOC_COMMENT
                && !$token->wasFirstOnLine()
                && !(
                    $this->Formatter->Psr12
                    && $prev
                    && $prev->id === \T_CLOSE_BRACE
                    && $prev->Flags & Flag::STRUCTURAL_BRACE
                    && $prev->Statement
                    && $prev->Statement->Flags & Flag::DECLARATION
                    && $prev->Statement->Data[Data::DECLARATION_TYPE] & (
                        Type::_CLASS
                        | Type::_ENUM
                        | Type::_INTERFACE
                        | Type::_TRAIT
                    )
                )
            ) {
                $token->Whitespace |= Space::SPACE_BEFORE | Space::SPACE_AFTER;
                if ($token->wasLastOnLine()) {
                    $token->Whitespace |= Space::LINE_AFTER;
                }
                if ($prev && (
                    $prev->Flags & Flag::CODE
                    || $prev->OpenTag === $prev
                )) {
                    $this->CommentsBesideCode[] = $token;
                    $token->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
                }
                continue;
            }

            $token->Whitespace |= Space::LINE_BEFORE
                | Space::LINE_AFTER
                | Space::SPACE_BEFORE
                | Space::SPACE_AFTER;

            $next = $token->NextCode;

            if (
                $next
                && $next->OpenTag === $token->OpenTag
                && !$this->Idx->CloseBracketOrVirtual[$next->id]
            ) {
                $this->Comments[] = [$token, $next];
            }

            if (
                $token->id !== \T_DOC_COMMENT
                && !($token->Flags & Flag::C_DOC_COMMENT)
            ) {
                continue;
            }

            if ($token->Flags & Flag::COLLAPSIBLE_COMMENT) {
                $this->CollapsibleComments[] = $token;
            } elseif (
                $token->hasNewline() && !(
                    $token->PrevSibling
                    && $token->NextSibling
                    && $token->PrevSibling->Statement === $token->NextSibling->Statement
                )
            ) {
                $token->Whitespace |= Space::BLANK_BEFORE;
            }

            if (
                $next
                && $next->Flags & Flag::DECLARATION
                && ($type = $next->Data[Data::DECLARATION_TYPE]) & (
                    Type::_DECLARE
                    | Type::_NAMESPACE
                    | Type::_USE
                )
                && $type !== Type::USE_TRAIT
            ) {
                $token->Whitespace |= Space::BLANK_AFTER;
            } elseif (
                $next
                && $next === $token->Next
                && $token->id === \T_DOC_COMMENT
            ) {
                $token->Whitespace |= Space::NO_BLANK_AFTER;
            }
        }
    }

    /**
     * Apply the rule to the given tokens
     *
     * Placement of comments saved earlier is finalised.
     *
     * A level of indentation is added to comments before `switch` cases unless
     * they appear after the opening brace of the switch, between a blank line
     * and the next case, or after a statement that unconditionally exits the
     * previous case.
     */
    public function beforeRender(array $tokens): void
    {
        foreach ($this->CommentsBesideCode as $token) {
            if ($token->hasNewlineBefore()) {
                $next = $token->NextCode;
                if ($next && !$this->Idx->CloseBracketOrVirtual[$next->id]) {
                    $this->Comments[] = [$token, $next];
                }
            } elseif ($token->hasNewlineAfter()) {
                $token->applyWhitespace(Space::SPACE_BEFORE);
                $token->Padding = $this->Formatter->SpacesBesideCode - 1;
            }
        }

        foreach ($this->Comments as [$token, $next]) {
            $indent = 0;
            if ($this->Idx->CaseOrDefault[$next->id] && $next->inSwitch()) {
                /** @var Token */
                $prev = $token->PrevCode;
                if ($prev !== $token->Parent) {
                    /** @var Token */
                    $statement = $prev->skipPrevEmptyStatements()->Statement;
                    $blankBefore = $prev->collect($token)->hasBlankLineBetweenTokens();
                    $blankAfter = $token->collect($next)->hasBlankLineBetweenTokens();
                    if (!(
                        ($blankBefore && !$blankAfter) || (
                            $this->Idx->SwitchCaseExit[$statement->id]
                            && ($blankBefore || !$blankAfter)
                        )
                    )) {
                        $indent = 1;
                    }
                }
            }

            [
                $token->TagIndent,
                $token->PreIndent,
                $token->Indent,
                $token->Deindent,
                $token->HangingIndent,
                $token->LinePadding,
                $token->LineUnpadding,
            ] = [
                $next->TagIndent,
                $next->PreIndent,
                $next->Indent + $indent,
                $next->Deindent,
                $next->HangingIndent,
                $next->LinePadding,
                $next->LineUnpadding,
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
}
