<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;
use const Lkrms\Pretty\Php\T_NULL;

/**
 * Apply spacing to structural braces based on their context
 *
 * Specifically:
 * - Place open braces on their own line if they
 *   - follow a declaration (e.g. `class` or `function`),
 *   - do not enclose an anonymous function, and
 *   - are not part of a `use` statement
 * - Otherwise, place open braces at the end of a line
 * - Suppress blank lines after open braces and before close braces
 * - Allow control structures to continue on the same line as close braces
 * - Suppress horizontal whitespace between empty braces (`{}`)
 * - Suppress vertical whitespace between `)` and `{` if they appear
 *   consecutively on their own lines
 *
 */
final class BracePosition implements TokenRule
{
    use TokenRuleTrait {
        destroy as private _destroy;
    }

    /**
     * @var array<Token[]>
     * @psalm-var array<array{0:Token,1:Token}>
     */
    private $BracketBracePairs = [];

    public function getTokenTypes(): ?array
    {
        return [
            T['{'],
            T['}'],
        ];
    }

    public function processToken(Token $token): void
    {
        if (!$token->isStructuralBrace() &&
                !$token->prevSibling(2)->is(T_MATCH)) {
            return;
        }

        $next = $token->next();
        if ($token->is(T['{'])) {
            $prev = $token->prev();
            if ($prev->is(T[')'])) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
            $line = WhitespaceType::NONE;
            // Add a newline before this open brace if:
            // 1. it's part of a declaration
            // 2. it isn't part of an anonymous function
            $parts = $token->declarationParts();
            if ($parts->hasOneOf(...TokenType::DECLARATION) &&
                    !$parts->last()->is(T_FUNCTION)) {
                // 3. it isn't part of a `use` statement
                $start = $parts->first();
                if (!$start->is(T_USE)) {
                    // 4. the token before the declaration is:
                    //    - `;`
                    //    - `{`
                    //    - `}`
                    //    - a T_CLOSE_TAG statement terminator
                    //    - non-existent (no code precedes the declaration), or
                    //    - the last token of an attribute
                    $prevCode = $start->prevCode();
                    if ($prevCode->is([T[';'], T['{'], T['}'], T_CLOSE_TAG, T_NULL]) ||
                            ($prevCode->OpenedBy && $prevCode->OpenedBy->is(T_ATTRIBUTE))) {
                        $line = WhitespaceType::LINE;
                    }
                }
            }
            $token->WhitespaceBefore   |= WhitespaceType::SPACE | $line;
            $token->WhitespaceAfter    |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            if ($next->is(T['}'])) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
            }

            return;
        }

        $token->WhitespaceBefore   |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

        if ($next->is([T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY]) ||
                ($next->is(T_WHILE) && $token->prevSibling()->is(T_DO))) {
            $token->WhitespaceAfter    |= WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->BracketBracePairs as [$bracket, $brace]) {
            if ($bracket->hasNewlineBefore() && $brace->hasNewlineBefore()) {
                $brace->WhitespaceBefore  |= WhitespaceType::SPACE;
                $brace->WhitespaceMaskPrev = WhitespaceType::SPACE;
            }
        }
    }

    public function destroy(): void
    {
        unset($this->BracketBracePairs);
        $this->_destroy();
    }
}
