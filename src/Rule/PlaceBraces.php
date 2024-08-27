<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
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
final class PlaceBraces implements TokenRule
{
    use TokenRuleTrait;

    /** @var array<array{Token,Token}> */
    private array $BracketBracePairs = [];

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
            if (!(
                $token->Flags & TokenFlag::STRUCTURAL_BRACE
                || $token->isMatchBrace()
            )) {
                continue;
            }

            if ($token->id === \T_CLOSE_BRACE) {
                // Suppress blank lines before close braces
                $token->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

                // Continue without moving subsequent code to the next line if
                // the brace is part of an expression
                if (!($token->Flags & TokenFlag::STATEMENT_TERMINATOR)) {
                    continue;
                }

                // Keep structures like `} else {` on the same line too
                $nextCode = $token->NextCode;
                if ($nextCode && $nextCode->continuesControlStructure()) {
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    if (!($nextCode->Flags & TokenFlag::HAS_UNENCLOSED_BODY)) {
                        $nextCode->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                    } else {
                        /** @todo Be more opinionated here */
                        $nextCode->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                    }
                    continue;
                }

                // Otherwise, add newlines after close braces
                $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
                continue;
            }

            $next = $token->Next;
            $parts = $token->skipPrevSiblingsToDeclarationStart()->declarationParts();

            // Move empty bodies to the end of the previous line
            if ($this->Formatter->CollapseEmptyDeclarationBodies
                    && $next->id === \T_CLOSE_BRACE
                    && $parts->hasOneFrom($this->Idx->DeclarationClassOrFunction)) {
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
                && ($t = $parts->last())
                && ($t = $t->NextSibling)
                    ? $t->ClosedBy ?? $t
                    : $token->PrevCode;
            if ($prev && $prev->id === \T_CLOSE_PARENTHESIS) {
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

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->BracketBracePairs = [];
    }
}
