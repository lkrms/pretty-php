<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;

/**
 * Apply symmetrical whitespace to brackets and increase the indentation
 * level of tokens between them
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
            self::PROCESS_TOKENS => 600,
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
     * @inheritDoc
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

            if ($prev->CloseBracket) {
                if ($hasNewline = $token->hasNewlineAfterPrevCode()) {
                    $token->Indent++;
                }

                if (!$this->Idx->Virtual[$prev->id]) {
                    $this->mirrorBracket($prev, $hasNewline);
                }
            }
        }
    }
}
