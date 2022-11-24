<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;

class StripHeredocIndents implements TokenFilter
{
    private $Heredoc;

    public function __invoke(&$token): bool
    {
        if (is_null($this->Heredoc) && (!is_array($token) || $token[0] !== T_START_HEREDOC))
        {
            return true;
        }
        if (is_null($this->Heredoc))
        {
            $this->Heredoc = [];
            return true;
        }
        if (!is_array($token))
        {
            $this->Heredoc[] = & $token;
            return true;
        }
        $this->Heredoc[] = & $token[1];
        if ($token[0] === T_END_HEREDOC)
        {
            if (preg_match('/^\h+/', $token[1], $matches))
            {
                $stripped = preg_replace("/^{$matches[0]}/m", "", $this->Heredoc);
                foreach ($this->Heredoc as $i => & $code)
                {
                    $code = $stripped[$i];
                }
            }
            $this->Heredoc = null;
        }

        return true;
    }
}
