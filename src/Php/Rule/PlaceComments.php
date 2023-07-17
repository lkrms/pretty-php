<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Place comments beside code, above code, or inside code
 *
 */
final class PlaceComments implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var Token[]
     */
    private $CommentsBesideCode = [];

    /**
     * [Comment token, subsequent code token]
     *
     * @var array<array{Token,Token}>
     */
    private $Comments = [];

    public function getPriority(string $method): ?int
    {
        if ($method === self::BEFORE_RENDER) {
            return 997;
        }

        return 90;
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
        if ($token->wasBetweenTokensOnLine(true)) {
            if ($token->_prev->IsCode ||
                    $token->_prev->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                $this->CommentsBesideCode[] = $token;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                return;
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            return;
        }

        // Don't move comments beside code to the next line
        if (!$token->wasFirstOnLine() && $token->wasLastOnLine()) {
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            if ($token->_prev->IsCode ||
                    $token->_prev->is([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
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
        if ($next &&
                !($next->isCloseBracket() || $next->endsAlternativeSyntax())) {
            $this->Comments[] = [$token, $next];
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if ($token->id !== T_DOC_COMMENT) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->PinToCode = !($next &&
                ($next->isCloseBracket() || $next->endsAlternativeSyntax()));
            return;
        }

        $line = WhitespaceType::LINE;
        if ($token->hasNewline() &&
                $token->_prev->EndStatement === $token->_prev /*&&
                $token->_prev->id !== T_COMMA*/) {
            $line = WhitespaceType::BLANK;
        }
        $token->WhitespaceBefore |= WhitespaceType::SPACE | $line;

        // Add a blank line after file-level docblocks
        $next = $token->_next;
        if ($next &&
            ($next->is([T_DECLARE, T_NAMESPACE]) ||
                ($next->id === T_USE &&
                    $next->parent()->declarationParts()->first(true)->is([T_NAMESPACE, T_NULL])))) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;
            return;
        }

        if ($next && $next->IsCode) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $token->PinToCode = !($next->isCloseBracket() || $next->endsAlternativeSyntax());
        }
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->CommentsBesideCode as $token) {
            if (!$token->hasNewlineBefore()) {
                if ($token->hasNewlineAfter()) {
                    $token->WhitespaceBefore |= WhitespaceType::TAB;
                } else {
                    $token->WhitespaceBefore |= WhitespaceType::SPACE;
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                }
            }
        }

        /**
         * @var Token $token
         * @var Token $next
         */
        foreach ($this->Comments as [$token, $next]) {
            // Comments are usually aligned to the code below them, but switch
            // blocks are a special case, e.g.:
            //
            // ```
            // switch ($a) {
            //     //
            //     case 0:
            //     case 1:
            //         //
            //         func();
            //         // Aligns with previous statement
            //     case 2:
            //     //
            //     case 3:
            //         func2();
            //         break;
            //
            //         // Aligns with previous statement
            //
            //     case 4:
            //         func();
            //         break;
            //
            //     //
            //     default:
            //         break;
            // }
            // ```
            if ($next->id === T_CASE ||
                    ($next->id === T_DEFAULT && $next->parent()->prevSibling(2)->id === T_SWITCH)) {
                $prev = $token->prevCode();
                if ($prev !== $next->parent() &&
                        ($next->hasBlankLineBefore() || !$prev->hasBlankLineAfter()) &&
                        !($prev->is([T_COLON, T_SEMICOLON]) && $prev->inSwitchCase())) {
                    continue;
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
                $next->Indent,
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
