<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Suppress changes to whitespace within strings and heredocs
 *
 */
final class ProtectStrings implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var Token[]
     */
    private $Strings = [];

    /**
     * @var Token[]
     */
    private $Heredocs = [];

    public function getPriority(string $method): ?int
    {
        return 40;
    }

    public function processToken(Token $token): void
    {
        if ($string = end($this->Strings)) {
            $token->StringOpenedBy = $string;
        }

        if ($heredoc = end($this->Heredocs)) {
            $token->HeredocOpenedBy = $heredoc;
        }

        if (($isStringDelimiter = $token->is([T_DOUBLE_QUOTE, T_BACKTICK])) &&
                (!$string || $string->BracketStack !== $token->BracketStack)) {
            $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
            $this->Strings[] = $token;

            return;
        }

        if ($token->id === T_START_HEREDOC) {
            $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
            $this->Heredocs[] = $token;

            return;
        }

        if (!($string || $heredoc)) {
            return;
        }

        if ($isStringDelimiter) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            array_pop($this->Strings);

            return;
        }

        if ($token->id === T_END_HEREDOC) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            array_pop($this->Heredocs);

            return;
        }

        if ($this->shouldProtect($token, $string ?: null) ||
                $this->shouldProtect($token, $heredoc ?: null)) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            if (!$token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
            }
        }
    }

    public function reset(): void
    {
        $this->Strings = [];
        $this->Heredocs = [];
    }

    private function shouldProtect(Token $token, ?Token $openedBy): bool
    {
        return $openedBy &&
            ($token->BracketStack === $openedBy->BracketStack ||
                (array_slice($token->BracketStack, 0, -1) === $openedBy->BracketStack &&
                    end($token->BracketStack)->id === T_OPEN_BRACKET));
    }
}
