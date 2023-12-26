<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Increase the indentation level of tokens enclosed in brackets
 *
 * Also, apply symmetrical vertical whitespace to brackets where inner newlines
 * have been added or removed by rules other than {@see PreserveLineBreaks} and
 * {@see PreserveOneLineStatements}.
 */
final class StandardIndentation implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
                return 600;

            default:
                return null;
        }
    }

    public function processToken(Token $token): void
    {
        if ($token->OpenedBy) {
            $token->Indent = $token->OpenedBy->Indent;
            return;
        }

        if (!$token->_prev) {
            return;
        }

        $prev = $token->_prev;
        $token->Indent = $prev->Indent;

        if (!$prev->ClosedBy) {
            return;
        }

        if ($prev->hasNewlineBeforeNextCode()) {
            $token->Indent++;
            if (!$prev->NewlineAfterPreserved) {
                $this->mirrorBracket($prev, true);
            }
            return;
        }

        if ($prev->NewlineAfterPreserved) {
            $this->mirrorBracket($prev, false);
        }
    }
}
