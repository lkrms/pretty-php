<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

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
final class PlaceBraces implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var array<Token[]>
     * @psalm-var array<array{0:Token,1:Token}>
     */
    private $BracketBracePairs = [];

    public function getPriority(string $method): ?int
    {
        return 94;
    }

    public function getTokenTypes(): array
    {
        return [
            T_OPEN_BRACE,
            T_CLOSE_BRACE,
        ];
    }

    public function processToken(Token $token): void
    {
        if (!($match = $token->prevSibling(2)->id === T_MATCH) &&
                !$token->isStructuralBrace(false)) {
            return;
        }

        $next = $token->next();
        if ($token->id === T_OPEN_BRACE) {
            // Move empty bodies to the end of the previous line
            $parts = $token->Expression->declarationParts();
            if ($next->id === T_CLOSE_BRACE &&
                    $parts->hasOneOf(T_CLASS, T_ENUM, T_FUNCTION, T_INTERFACE, T_TRAIT)) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev = WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;

                return;
            }

            // Otherwise, add a newline before this open brace if:
            // 1. it's part of a declaration
            // 2. it isn't part of an anonymous function
            // 3. it isn't part of a `use` statement, and
            // 4. either:
            //    - it's part of an anonymous class declaration that
            //      spans multiple lines, or
            //    - the token before the declaration is:
            //      - `;`
            //      - `{`
            //      - `}`
            //      - a T_CLOSE_TAG statement terminator, or
            //      - non-existent (no code precedes the declaration)
            $line = WhitespaceType::NONE;

            if (!$this->Formatter->OneTrueBraceStyle &&
                    $parts->hasOneOf(...TokenType::DECLARATION) &&
                    !$parts->last()->is([T_DECLARE, T_FUNCTION])) {
                $start = $parts->first();
                if ($start->id !== T_USE &&
                    ((!($prevCode = $start->_prevCode) ||
                            $prevCode->is([T_SEMICOLON, T_OPEN_BRACE, T_CLOSE_BRACE, T_CLOSE_TAG])) ||
                        ($start->id === T_NEW && $parts->hasNewlineBetweenTokens()))) {
                    $line = WhitespaceType::LINE;
                }
            }
            $prev = $parts->hasOneOf(T_FUNCTION)
                ? $parts->last()->nextSibling()->canonicalClose()
                : $token->prevCode();
            if ($prev->id === T_CLOSE_PARENTHESIS) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
            $token->WhitespaceBefore |= WhitespaceType::SPACE | $line;
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            if ($next->id === T_CLOSE_BRACE) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
            }

            return;
        }

        $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
        $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

        if ($match ||
                ($nextCode = $token->nextCode())->is([T_CLOSE_PARENTHESIS, T_CLOSE_BRACKET]) ||
                $nextCode === $token->EndStatement) {
            return;
        }

        if ($next->continuesControlStructure()) {
            $token->WhitespaceAfter |= WhitespaceType::SPACE;
            if (!$next->BodyIsUnenclosed) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
            }

            return;
        }

        $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }

    public function beforeRender(array $tokens): void
    {
        foreach ($this->BracketBracePairs as [$bracket, $brace]) {
            if ($bracket->hasNewlineBefore() && $brace->hasNewlineBefore()) {
                $brace->WhitespaceBefore |= WhitespaceType::SPACE;
                $brace->WhitespaceMaskPrev = WhitespaceType::SPACE;
            }
        }
    }

    public function reset(): void
    {
        $this->BracketBracePairs = [];
    }
}
