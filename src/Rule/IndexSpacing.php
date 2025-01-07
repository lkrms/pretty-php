<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;

/**
 * Apply whitespace to tokens as per the formatter's token index
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
            self::PROCESS_TOKENS => 100,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->merge(
            [
                \T_COLON => true,
                \T_CLOSE_ALT => true,
            ],
            $idx->AddSpace,
            $idx->AddSpaceBefore,
            $idx->AddSpaceAfter,
            $idx->SuppressSpaceBefore,
            $idx->SuppressSpaceAfter,
            $idx->OpenBracket,
            $idx->CloseBracket,
        );
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return false;
    }

    /**
     * Apply the rule to the given tokens
     *
     * Leading and trailing spaces are:
     *
     * - added to tokens in the `AddSpace`, `AddSpaceBefore` and `AddSpaceAfter`
     *   indexes
     * - suppressed, along with blank lines, for tokens in the
     *   `SuppressSpaceBefore` and `SuppressSpaceAfter` indexes, and inside
     *   brackets other than structural and `match` braces
     *
     * Blank lines are also suppressed inside alternative syntax blocks.
     */
    public function processTokens(array $tokens): void
    {
        $idx = $this->Idx;

        foreach ($tokens as $token) {
            if ($idx->AddSpace[$token->id]) {
                $token->Whitespace |= Space::SPACE_BEFORE | Space::SPACE_AFTER;
            } elseif ($idx->AddSpaceBefore[$token->id]) {
                $token->Whitespace |= Space::SPACE_BEFORE;
            } elseif ($idx->AddSpaceAfter[$token->id]) {
                $token->Whitespace |= Space::SPACE_AFTER;
            }

            if ($idx->SuppressSpaceAfter[$token->id] || (
                $idx->OpenBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || $token->isMatchOpenBrace()
                )
            )) {
                $token->Whitespace |= Space::NO_BLANK_AFTER | Space::NO_SPACE_AFTER;
            } elseif ($token->id === \T_COLON && $token->CloseBracket) {
                $token->Whitespace |= Space::NO_BLANK_AFTER;
            }

            if ($idx->SuppressSpaceBefore[$token->id] || (
                $idx->CloseBracket[$token->id] && !(
                    $token->Flags & TokenFlag::STRUCTURAL_BRACE
                    || ($token->OpenBracket && $token->OpenBracket->isMatchOpenBrace())
                )
            )) {
                $token->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_SPACE_BEFORE;
            } elseif ($token->id === \T_CLOSE_ALT) {
                $token->Whitespace |= Space::NO_BLANK_BEFORE;
            }
        }
    }
}
