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
                // The pattern below won't match the first line or closing
                // identifier unless newlines are added temporarily
                $keys = [0];
                if (count($this->Heredoc) > 1)
                {
                    $keys[] = count($this->Heredoc) - 1;
                }
                foreach ($keys as $key)
                {
                    $this->Heredoc[$key] = "\n" . $this->Heredoc[$key];
                }
                $stripped = preg_replace("/\\n{$matches[0]}/", "\n", $this->Heredoc);
                foreach ($keys as $key)
                {
                    $stripped[$key] = substr($stripped[$key], 1);
                }
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
