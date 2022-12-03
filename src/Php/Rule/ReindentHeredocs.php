<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\WhitespaceType;

class ReindentHeredocs implements TokenRule
{
    /**
     * @var bool|null
     */
    private $InHeredoc;

    public function __invoke(Token $token): void
    {
        if (!$this->InHeredoc && !$token->is(T_START_HEREDOC)) {
            return;
        }
        $token->HeredocOpenedBy = $token->prev()->HeredocOpenedBy ?: $token;
        if (!$this->InHeredoc) {
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
            $this->InHeredoc           = true;
        } elseif ($token->is(T_END_HEREDOC)) {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $this->InHeredoc           = false;
        } else {
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceMaskNext = WhitespaceType::NONE;
        }
        if (!$token->Indent) {
            return;
        }
        $token->Code = str_replace("\n", "\n" . $token->HeredocOpenedBy->indent(), $token->Code);
    }
}
