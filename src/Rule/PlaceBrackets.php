<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Token;

/**
 * Make brackets symmetrical
 *
 * @api
 */
final class PlaceBrackets implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 240,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getTokens(AbstractTokenIndex $idx): array
    {
        return $idx->OpenBracket;
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
     * Inner whitespace is copied from open brackets to close brackets.
     *
     * Structural and `match` expression braces are ignored.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->Flags & Flag::STRUCTURAL_BRACE
                || ($token->id === \T_OPEN_BRACE && $token->isMatchOpenBrace())
            ) {
                continue;
            }

            /** @var Token */
            $close = $token->CloseBracket;
            if (!$token->hasNewlineBeforeNextCode()) {
                $close->Whitespace |= Space::NO_BLANK_BEFORE | Space::NO_LINE_BEFORE;
            } else {
                $close->Whitespace |= Space::LINE_BEFORE;
                if (!$close->hasNewlineBefore()) {
                    $close->removeWhitespace(Space::NO_LINE_BEFORE);
                }
            }
        }
    }
}
