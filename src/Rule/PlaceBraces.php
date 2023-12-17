<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply whitespace to structural braces
 *
 * Open braces are placed on their own line if they:
 *
 * - follow a declaration (e.g. `class` or `function`),
 * - do not enclose an anonymous class declared on a single line,
 * - do not enclose an anonymous function, and
 * - are not part of a `use` statement
 *
 * Blank lines after open braces and before close braces are suppressed, and
 * newlines are added after close braces unless they are part of a continuing
 * control structure or expression.
 *
 * Horizontal whitespace between empty braces is suppressed, and empty class and
 * function bodies are moved to the end of the previous line.
 *
 * Consecutive `)` and `{` tokens appearing on their own lines are collapsed to
 * `) {`.
 *
 * @api
 */
final class PlaceBraces implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    /**
     * @var array<Token[]>
     * @psalm-var array<array{0:Token,1:Token}>
     */
    private $BracketBracePairs = [];

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 92;

            case self::BEFORE_RENDER:
                return 400;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_OPEN_BRACE,
            \T_CLOSE_BRACE,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            $isMatch = $token->isMatchBrace();
            if (!$isMatch &&
                    !$token->isStructuralBrace(false)) {
                continue;
            }

            if ($token->id === \T_CLOSE_BRACE) {
                // Suppress blank lines before close braces
                $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                // Continue without moving subsequent code to the next line if
                // the brace is part of an expression
                if ($isMatch) {
                    continue;
                }
                $nextCode = $token->_nextCode;
                if ($nextCode &&
                    (($nextCode->OpenedBy && $nextCode->id !== \T_CLOSE_BRACE) ||
                        $nextCode === $token->EndStatement)) {
                    continue;
                }

                // Keep structures like `} else {` on the same line too
                if ($nextCode && $nextCode->continuesControlStructure()) {
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    if (!$nextCode->BodyIsUnenclosed) {
                        $nextCode->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                    }
                    continue;
                }

                // Otherwise, add newlines after close braces
                $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
                continue;
            }

            $next = $token->_next;
            $parts = $token->Expression->declarationParts();

            // Move empty bodies to the end of the previous line
            if ($next->id === \T_CLOSE_BRACE &&
                    $parts->hasOneOf(\T_CLASS, \T_ENUM, \T_FUNCTION, \T_INTERFACE, \T_TRAIT)) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev = WhitespaceType::SPACE;
                $token->WhitespaceMaskNext = WhitespaceType::NONE;
                continue;
            }

            // Add newlines and suppress blank lines after open braces
            $token->WhitespaceBefore |= WhitespaceType::SPACE;
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;

            // Suppress horizontal whitespace between empty braces
            if ($next->id === \T_CLOSE_BRACE) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
            }

            $prev = $parts->hasOneOf(\T_FUNCTION)
                ? $parts->last()->nextSibling()->canonicalClose()
                : $token->prevCode();
            if ($prev->id === \T_CLOSE_PARENTHESIS) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
        }
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
