<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;

/**
 * Apply symmetrical whitespace to brackets and increase the indentation
 * level of tokens between them
 *
 * @api
 */
final class StandardIndentation implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 600;

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if ($token->OpenedBy) {
                $token->Indent = $token->OpenedBy->Indent;
                continue;
            }

            if (!$token->Prev) {
                continue;
            }

            $prev = $token->Prev;
            $token->Indent = $prev->Indent;

            if (!$prev->ClosedBy) {
                continue;
            }

            if ($prev->hasNewlineBeforeNextCode()) {
                $token->Indent++;
                $this->mirrorBracket($prev, true);
                continue;
            }

            $this->mirrorBracket($prev, false);
        }
    }
}
