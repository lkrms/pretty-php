<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply whitespace to tokens as per the formatter's token type index
 *
 * @api
 */
final class IndexSpacing implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 78,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return TokenTypeIndex::merge(
            $idx->AddSpace,
            $idx->AddSpaceBefore,
            $idx->AddSpaceAfter,
            $idx->SuppressSpaceBefore,
            $idx->SuppressSpaceAfter,
            $idx->OpenBracketOrAlt,
            $idx->CloseBracketOrAlt,
        );
    }

    /**
     * Apply the rule to the given tokens
     *
     * Leading and trailing spaces are added to tokens in the `AddSpace`,
     * `AddSpaceBefore` and `AddSpaceAfter` indexes, then suppressed, along with
     * adjacent blank lines, for tokens in the `SuppressSpaceBefore` and
     * `SuppressSpaceAfter` indexes, and inside brackets other than structural
     * and `match` braces. Blank lines are also suppressed after alternative
     * syntax colons and before their closing counterparts.
     */
    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            if ($idx->AddSpace[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceBefore[$token->id]) {
                $token->WhitespaceBefore |= WhitespaceType::SPACE;
            } elseif ($idx->AddSpaceAfter[$token->id]) {
                $token->WhitespaceAfter |= WhitespaceType::SPACE;
            }

            if ($idx->SuppressSpaceAfter[$token->id] || (
                $idx->OpenBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || $token->isMatchBrace()
                )
            )) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_COLON && $token->ClosedBy) {
                $token->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            }

            if ($idx->SuppressSpaceBefore[$token->id] || (
                $idx->CloseBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || $token->isMatchBrace()
                )
            )) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK & ~WhitespaceType::SPACE;
            } elseif ($token->id === \T_END_ALT_SYNTAX) {
                $token->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            }
        }
    }
}
