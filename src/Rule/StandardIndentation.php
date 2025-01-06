<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;

/**
 * Indent tokens between brackets with inner newlines
 *
 * @api
 */
final class StandardIndentation implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 300,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedTokens(): bool
    {
        return true;
    }

    /**
     * Apply the rule to the given tokens
     *
     * The `Indent` and inner whitespace of each open bracket is copied to its
     * close bracket, and the `Indent` of tokens between brackets with inner
     * newlines is incremented.
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->OpenBracket) {
                $token->Indent = $token->OpenBracket->Indent;
                continue;
            }

            if (!$token->Prev) {
                continue;
            }

            $prev = $token->Prev;
            $token->Indent = $prev->Indent;

            if ($close = $prev->CloseBracket) {
                if ($hasNewline = $token->hasNewlineAfterPrevCode()) {
                    $token->Indent++;
                }

                if (!$this->Idx->Virtual[$prev->id]) {
                    if (!$hasNewline) {
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
    }
}
