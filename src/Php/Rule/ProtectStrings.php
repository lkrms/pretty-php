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

    public function __invoke(Token $token): void
    {
        if (!$this->InString && !$token->is('"')) {
            return;
        }

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
}
