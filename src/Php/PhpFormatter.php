<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Pretty\Php\Filter\WhitespaceFilter;

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
    public $PlainTokens;

    /**
     * @var array
     */
    public $Tokens;

    public function format(string $code): string
    {
        $this->PlainTokens = token_get_all($code, TOKEN_PARSE);
        $this->Tokens      = array_filter($this->PlainTokens, new WhitespaceFilter());

        $bracketStack = [];
        $bracketLevel = 0;

        foreach ($this->Tokens as $index => & $token)
        {
            $token = new PhpToken(
                $index,
                $token,
                $last ?? null,
                $bracketLevel,
                $bracketStack,
                $this->PlainTokens
            );
            $last = $token;

            if ($token->isOpenBracket())
            {
                array_push($bracketStack, $token);
                $bracketLevel++;
            }

            if ($token->isCloseBracket())
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

