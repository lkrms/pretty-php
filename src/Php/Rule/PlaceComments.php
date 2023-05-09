<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Place comments beside code, above code, or inside code
 *
 */
final class PlaceComments implements TokenRule
{
    use TokenRuleTrait {
        destroy as private _destroy;
    }

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
        // Prevent recursion in `Token->renderComment()` when tokens are
        // rendered early, e.g. by `Formatter->logProgress()`
        $token->CommentPlaced = true;

        // Leave embedded comments alone
        if ($token->wasBetweenTokensOnLine(true)) {
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::SPACE;

            return;
        }

        // Don't move comments beside code to the next line
        if (!$token->wasFirstOnLine() && $token->wasLastOnLine()) {
            $token->WhitespaceBefore |= WhitespaceType::TAB;
            $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            $token->WhitespaceAfter |= WhitespaceType::LINE;

            return;
        }

        // Just before rendering, indentation and padding values are copied from
        // `$next` to `$token` for each comment in `$this->Comments`. Add this
        // comment unless `$next` is a close bracket.
        $next = $token->nextCode();
        if (!$next->IsNull &&
                !$next->isCloseBracket() &&
                !$next->endsAlternativeSyntax()) {
            $this->Comments[] = [$token, $next];
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE;
        if (!$token->is(T_DOC_COMMENT)) {
            $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->PinToCode = !$next->isCloseBracket() && !$next->endsAlternativeSyntax();

            return;
        }

        $line = WhitespaceType::LINE;
        if ($token->hasNewline() &&
            !($prev = $token->prev())->IsNull &&
            !($prev === $token->parent()) &&
            !($prev->is(T[',']) ||
                ($prev->is([T[':'], T[';']]) &&
                    ($prev->inSwitchCase() || $prev->inLabel())))) {
            $line = WhitespaceType::BLANK;
        }
        $token->WhitespaceBefore |= WhitespaceType::SPACE | $line;

        // PHPDoc comments immediately before namespace declarations are
        // generally associated with the file, not the namespace
        if ($token->next()->isDeclaration(T_NAMESPACE)) {
            $token->WhitespaceAfter |= WhitespaceType::BLANK;

            return;
        }

        if ($token->next()->IsCode) {
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            $token->PinToCode = !$next->isCloseBracket() && !$next->endsAlternativeSyntax();
        }
    }

    public function beforeRender(array $tokens): void
    {
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
                        !($prev->is([T[':'], T[';']]) && $prev->inSwitchCase())) {
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

    public function destroy(): void
    {
        unset($this->Comments);
        $this->_destroy();
    }
}
