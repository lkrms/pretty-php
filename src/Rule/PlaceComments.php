<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\CommentType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Place comments beside code, above code, or inside code
 *
 * @api
 */
final class PlaceComments implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var Token[]
     */
    private $CommentsBesideCode = [];

    /**
     * [ [ Comment token, subsequent code token ] ]
     *
     * @var array<Token[]>
     */
    private $Comments = [];

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 90;

            case self::BEFORE_RENDER:
                return 997;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return TokenType::COMMENT;
    }

    public function processToken(Token $token): void
    {
        if ($token->isOneLineComment() &&
                $token->_next &&
                $token->_next->id !== T_CLOSE_TAG) {
            $token->CriticalWhitespaceAfter |= WhitespaceType::LINE;
        }

        // Leave embedded comments alone
        if (!($wasFirstOnLine = $token->wasFirstOnLine()) && !$token->wasLastOnLine()) {
            if ($token->_prev->IsCode || $token->_prev->OpenTag === $token->_prev) {
                $this->CommentsBesideCode[] = $token;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                return;
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            return;
        }

        // Don't move comments beside code to the next line (except docblocks
        // that break over multiple lines)
        if (!$wasFirstOnLine &&
            ($token->CommentType !== CommentType::DOC_COMMENT ||
                !$token->hasNewline())) {
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            if ($token->_prev->IsCode || $token->_prev->OpenTag === $token->_prev) {
                $this->CommentsBesideCode[] = $token;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                return;
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            return;
        }

        // Copy indentation and padding from `$next` to `$token` in
        // `beforeRender()` unless `$next` is a close bracket
        $next = $token->_nextCode;
        if ($next && !$next->isCloseBracketOrEndsAlternativeSyntax()) {
            $this->Comments[] = [$token, $next];
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if ($token->id !== T_DOC_COMMENT) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            return;
        }

        // Add a blank line before multi-line docblocks
        if ($token->hasNewline() &&
                $token->_prev->EndStatement === $token->_prev) {
            $token->WhitespaceBefore |= WhitespaceType::BLANK | WhitespaceType::SPACE;
        } else {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
        }

        // Add a blank line after file-level docblocks
        if ($next &&
            ($next->is([T_DECLARE, T_NAMESPACE]) ||
                ($next->id === T_USE &&
                    (!($first = $next->parent()->startOfExpression()->declarationParts()->first()) ||
                        $first->id === T_NAMESPACE)))) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;
            return;
        }

        // Otherwise, pin docblocks to subsequent code
        if ($next &&
                $next === $token->_next) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->CommentsBesideCode as $token) {
            if (!$token->hasNewlineBefore()) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                if ($token->hasNewlineAfter()) {
                    $token->_prev->WhitespaceMaskNext |= WhitespaceType::SPACE;
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
            if ($next->id === T_CASE ||
                ($next->id === T_DEFAULT &&
                    $next->parent()->prevSibling(2)->id === T_SWITCH)) {
                $prev = $token->_prevCode;
                if (!(end($token->BracketStack) === $prev ||
                    ($prev->collect($token)->hasBlankLineBetweenTokens() &&
                        !$token->collect($next)->hasBlankLineBetweenTokens()))) {
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
    }

    public function reset(): void
    {
        $this->CommentsBesideCode = [];
        $this->Comments = [];
    }
}
