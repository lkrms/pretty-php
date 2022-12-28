<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\TokenRuleTrait;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

/**
 * Suppress changes to whitespace within strings and heredocs
 *
 * Assigns:
 * - {@see Token::$StringOpenedBy}
 * - {@see Token::$HeredocOpenedBy}
 * - {@see Token::$WhitespaceMaskPrev} = {@see WhitespaceType::NONE};
 * - {@see Token::$WhitespaceMaskNext} = {@see WhitespaceType::NONE};
 */
class ProtectStrings implements TokenRule
{
    use TokenRuleTrait;

    /**
     * @var bool|null
     */
    private $InString;

    /**
     * @var bool|null
     */
    private $InHeredoc;

    public function processToken(Token $token): void
    {
        if ($this->InString || $token->is('"')) {
            $this->protectString($token);
        } elseif ($this->InHeredoc || $token->is(T_START_HEREDOC)) {
            $this->protectHeredoc($token);
        }
    }

    private function protectString(Token $token): void
    {
        $token->StringOpenedBy = $token->prev()->StringOpenedBy ?: $token;

        if (!$this->InString) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            $this->InString            = true;

            return;
        }

        if ($token->is('"')) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $this->InString            = false;

            return;
        }

        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceMaskNext = WhitespaceType::NONE;
    }

    private function protectHeredoc(Token $token): void
    {
        $token->HeredocOpenedBy = $token->prev()->HeredocOpenedBy ?: $token;

        if (!$this->InHeredoc) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            $this->InHeredoc           = true;

            return;
        }

        if ($token->is(T_END_HEREDOC)) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $this->InHeredoc           = false;

            return;
        }

        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceMaskNext = WhitespaceType::NONE;
    }
}
