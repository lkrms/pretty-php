<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
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

    public function getPriority(string $method): ?int
    {
        return 600;
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

        if ($prev->hasNewlineAfterCode()) {
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
