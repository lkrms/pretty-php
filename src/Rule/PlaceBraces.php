<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

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
    public static function getTokens(TokenIndex $idx): array
    {
        return [
            \T_OPEN_BRACE => true,
        ];
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
        $this->BracketBracePairs = [];
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
     *   on the same line as the declaration they belong to unless
     *   `CollapseEmptyDeclarationBodies` is disabled.
     * - Horizontal whitespace is suppressed between other empty braces.
     *
     * > Open brace placement is handled by `VerticalSpacing`, which runs after
     * > newlines are applied by other rules.
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
            $close = $token->CloseBracket;

            // Suppress blank lines before close braces
            $close->Whitespace |= Space::NO_BLANK_BEFORE | Space::LINE_BEFORE | Space::SPACE_BEFORE;

            // Don't move subsequent code to the next line if the brace is part
            // of an expression
            if ($close->Flags & TokenFlag::STATEMENT_TERMINATOR) {
                // Keep structures like `} else {` on the same line
                $next = $close->NextCode;
                if ($next && $next->continuesControlStructure()) {
                    $close->Whitespace |= Space::SPACE_AFTER;
                    if (!($next->Flags & TokenFlag::UNENCLOSED_PARENT) || (
                        // `$next` can only be `elseif` or `else`, so if the
                        // close brace is not the body of `if` or `elseif`, the
                        // `if` construct `$next` belongs to must be its parent,
                        // and `$next` should be on a new line
                        $close->PrevSibling
                        && $close->PrevSibling->PrevSibling
                        && $this->Idx->IfOrElseIf[$close->PrevSibling->PrevSibling->id]
                    )) {
                        $next->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
                    } else {
                        $close->Whitespace |= Space::LINE_AFTER;
                        $next->Whitespace |= Space::NO_BLANK_BEFORE;
                    }
                } else {
                    // Otherwise, add newlines after close braces
                    $close->Whitespace |= Space::LINE_AFTER | Space::SPACE_AFTER;
                }
            }

            /** @var Token */
            $next = $token->Next;
            $parts = $token->skipToStartOfDeclaration()->declarationParts();

            // Move empty bodies to the end of the previous line
            if (
                $this->Formatter->CollapseEmptyDeclarationBodies
                && $next->id === \T_CLOSE_BRACE
                && (
                    $parts->hasOneFrom($this->Idx->DeclarationClassOrFunction)
                    || $token->inPropertyOrPropertyHook()
                )
            ) {
                $token->Whitespace |= Space::NONE_BEFORE | Space::NONE_AFTER;
                $token->applyWhitespace(Space::SPACE_BEFORE);
                continue;
            }

            // Add newlines and suppress blank lines after open braces
            $token->Whitespace |= Space::SPACE_BEFORE | Space::NO_BLANK_AFTER | Space::LINE_AFTER | Space::SPACE_AFTER;

            // Suppress horizontal whitespace between empty braces
            if ($next->id === \T_CLOSE_BRACE) {
                $token->Whitespace |= Space::NO_SPACE_AFTER;
            }

            // Collect consecutive `)` and `{` tokens to collapse before
            // rendering
            if ($parts->hasOneOf(\T_FUNCTION)) {
                /** @var Token */
                $prev = $parts->last()->NextSibling;
                $prev = $prev->CloseBracket;
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
                $brace->Whitespace |= Space::NONE_BEFORE;
                $brace->applyWhitespace(Space::SPACE_BEFORE);
            }
        }
    }
}
