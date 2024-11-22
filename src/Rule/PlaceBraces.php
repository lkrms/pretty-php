<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply whitespace to structural and match expression braces
 *
 * @api
 */
final class PlaceBraces implements TokenRule
{
    use TokenRuleTrait;

    /** @var array<array{Token,Token}> */
    private array $BracketBracePairs;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 92,
            self::BEFORE_RENDER => 400,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_OPEN_BRACE => true,
        ];
    }

    /**
     * Apply the rule to the given tokens
     *
     * Whitespace is applied to structural and `match` expression braces as
     * follows:
     *
     * - Blank lines are suppressed after open braces and before close braces.
     * - Newlines are added after open braces.
     * - Newlines are added after close braces unless they belong to a `match`
     *   expression or a control structure that is immediately continued, e.g.
     *   `} else {`. In the latter case, trailing newlines are suppressed.
     * - Empty class, function and property hook bodies are collapsed to ` {}`
     *   immediately after the declaration they belong to.
     * - Horizontal whitespace is suppressed between other empty braces.
     *
     * Open brace placement is left for a rule that runs after vertical
     * whitespace has been applied.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (!(
                $token->Flags & TokenFlag::STRUCTURAL_BRACE
                || $token->isMatchOpenBrace()
            )) {
                continue;
            }

            /** @var Token */
            $close = $token->ClosedBy;

            // Suppress blank lines before close braces
            $close->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $close->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;

            // Don't move subsequent code to the next line if the brace is part
            // of an expression
            if ($close->Flags & TokenFlag::STATEMENT_TERMINATOR) {
                // Keep structures like `} else {` on the same line
                $next = $close->NextCode;
                if ($next && $next->continuesControlStructure()) {
                    $close->WhitespaceAfter |= WhitespaceType::SPACE;
                    if (!($next->Flags & TokenFlag::HAS_UNENCLOSED_BODY) || (
                        // `$next` can only be `elseif` or `else`, so if the
                        // close brace is not the body of `if` or `elseif`, the
                        // `if` construct `$next` belongs to must be its parent,
                        // and `$next` should be on a new line
                        $close->PrevSibling
                        && $close->PrevSibling->PrevSibling
                        && $this->Idx->IfOrElseIf[$close->PrevSibling->PrevSibling->id]
                    )) {
                        $next->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::LINE;
                    } else {
                        $close->WhitespaceAfter |= WhitespaceType::LINE;
                        $next->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
                    }
                } else {
                    // Otherwise, add newlines after close braces
                    $close->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
                }
            }

            /** @var Token */
            $next = $token->Next;
            $parts = $token->skipPrevSiblingsToDeclarationStart()->declarationParts();

            // Move empty bodies to the end of the previous line
            if (
                $this->Formatter->CollapseEmptyDeclarationBodies
                && $next->id === \T_CLOSE_BRACE
                && (
                    $parts->hasOneFrom($this->Idx->DeclarationClassOrFunction)
                    || $token->inPropertyOrPropertyHook()
                )
            ) {
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

            // Collect consecutive `)` and `{` tokens to collapse before
            // rendering
            if ($parts->hasOneOf(\T_FUNCTION)) {
                /** @var Token */
                $prev = $parts->last()->NextSibling;
                $prev = $prev->ClosedBy;
            } else {
                $prev = $token->PrevCode;
            }
            if ($prev && $prev->id === \T_CLOSE_PARENTHESIS) {
                $this->BracketBracePairs[] = [$prev, $token];
            }
        }
    }

    /**
     * Apply the rule to the given tokens
     *
     * In function declarations where `)` and `{` appear at the start of
     * consecutive lines, they are collapsed to the same line.
     */
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
