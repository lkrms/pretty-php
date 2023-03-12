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
     * @var bool|null
     */
    private $InString;

    /**
     * @var bool|null
     */
    private $InHeredoc;

    public function getPriority(string $method): ?int
    {
        return 40;
    }

    public function processToken(Token $token): void
    {
        if ($this->InHeredoc || $token->is(T_START_HEREDOC)) {
            $token->HeredocOpenedBy = $token->prev()->HeredocOpenedBy ?: $token;

            if (!$this->InHeredoc) {
                $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
                $this->InHeredoc                   = true;

                return;
            }

            if ($token->is(T_END_HEREDOC)) {
                $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                $this->InHeredoc                   = false;

                return;
            }
        } elseif ($this->InString || $token->is(T['"'])) {
            $token->StringOpenedBy = $token->prev()->StringOpenedBy ?: $token;

            if (!$this->InString) {
                $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
                $this->InString                    = true;

                return;
            }

            if ($token->is(T['"'])) {
                $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
                $this->InString                    = false;

                return;
            }
        } else {
            return;
        }

        $token->CriticalWhitespaceMaskPrev = WhitespaceType::NONE;
        $token->CriticalWhitespaceMaskNext = WhitespaceType::NONE;
    }
}
