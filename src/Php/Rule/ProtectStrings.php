<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concept\AbstractTokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class ProtectStrings extends AbstractTokenRule
{
    /**
     * @var bool|null
     */
    private $InString;

    /**
     * @var bool|null
     */
    private $InHeredoc;

    public function __invoke(Token $token, int $stage): void
    {
        if ($this->InString || $token->is('"')) {
            $this->protectString($token);

            return;
        }

        if ($this->InHeredoc || $token->is(T_START_HEREDOC)) {
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
