<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

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

        if ($token->is([T['"'], T['`']]) &&
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

        if ($token->is([T['"'], T['`']])) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            array_pop($this->Strings);

            return;
        }

        if ($token->id === T_END_HEREDOC) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            array_pop($this->Heredocs);

            return;
        }

        if (($string && $token->BracketStack === $string->BracketStack) ||
                ($heredoc && $token->BracketStack === $heredoc->BracketStack)) {
            $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
            if (!$token->isOpenBracket()) {
                $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
            }
        }
    }
}
