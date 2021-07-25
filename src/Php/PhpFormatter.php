<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Pretty\Php\Filter\PhpSkipFilter;

class PhpFormatter
{
    /**
     * @var string
     */
    public $Tab = "    ";

    /**
     * @var bool
     */
    public $LineBeforeBrace = true;

    /**
     * @var array
     */
    public $Tokens;

    private function Parse(string $code, ?array & $array, callable $filter = null)
    {
        if (is_null($filter))
        {
            $array = token_get_all($code, TOKEN_PARSE);
        }
        else
        {
            $array = array_filter(token_get_all($code, TOKEN_PARSE), $filter);
        }
    }

    public function Format(string $code): string
    {
        $this->Parse($code, $this->Tokens, new PhpSkipFilter());
        $bracketStack = [];
        $bracketLevel = 0;

        foreach ($this->Tokens as $index => & $token)
        {
            $token = new PhpToken($index, $token, $last ?? null, $bracketLevel, $bracketStack);
            $last  = $token;

            if ($token->IsOpenBracket())
            {
                array_push($bracketStack, $token);
                $bracketLevel++;
            }

            if ($token->IsCloseBracket())
            {
                $opener           = array_pop($bracketStack);
                $opener->ClosedBy = $token;
                $token->OpenedBy  = $opener;
                $bracketLevel--;
            }
        }

        return $code;
    }
}

