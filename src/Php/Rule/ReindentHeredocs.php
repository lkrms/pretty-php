<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Token;

class ReindentHeredocs implements TokenRule
{
    /**
     * @var bool|null
     */
    private $InHeredoc;

    public function __invoke(Token $token): void
    {
        if (!$this->InHeredoc && !$token->is(T_START_HEREDOC))
        {
            return;
        }
        if (!$this->InHeredoc)
        {
            $this->InHeredoc = true;

            return;
        }
        if ($token->is(T_END_HEREDOC))
        {
            if ($token->Indent)
            {
                $token->Code = $token->indent() . $token->Code;
            }
            $this->InHeredoc = false;

            return;
        }
        if (!$token->Indent)
        {
            return;
        }
        $indent = $token->indent();
        // Only indent lines that aren't empty
        $token->Code = preg_replace('/\n(?=[\h\S])/', "\n" . $indent, $token->Code);
        if ($token->prev()->is(T_START_HEREDOC) && preg_match('/^(?=[\h\S])/', $token->Code))
        {
            $token->Code = $indent . $token->Code;
        }
    }

}
