<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Filter\RemoveCommentTokens;
use Lkrms\Pretty\Php\Filter\RemoveWhitespaceTokens;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use RuntimeException;

class Formatter
{
    /**
     * @var string
     */
    public $Tab = "    ";

    /**
     * @var string[]
     */
    public $Rules = [
        AddEssentialWhitespace::class,
    ];

    /**
     * @var array<int,string|array{0:int,1:string,2:int}>
     */
    public $PlainTokens = [];

    /**
     * @var Token[]
     */
    public $Tokens = [];

    public function format(string $code): string
    {
        $whitespaceFilter = new RemoveWhitespaceTokens();
        $commentFilter    = new RemoveCommentTokens();

        $this->PlainTokens = token_get_all($code, TOKEN_PARSE);
        $this->Tokens      = [];

        $bracketStack = [];
        $bracketLevel = 0;
        foreach ($this->filter($this->PlainTokens, $whitespaceFilter) as $index => $plainToken)
        {
            $this->Tokens[$index] = $token = new Token(
                $index,
                $plainToken,
                end($this->Tokens) ?: null,
                $bracketLevel,
                $bracketStack,
                $this->PlainTokens
            );

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

        foreach ($this->Rules as $rule)
        {
            $rule = new $rule();
            foreach ($this->Tokens as $token)
            {
                $rule($token);
            }
        }

        $out = "";
        foreach ($this->Tokens as $token)
        {
            $out .= $token->render();
        }

        $before = $this->strip($this->PlainTokens, $whitespaceFilter, $commentFilter);
        $after  = $this->strip(token_get_all($out, TOKEN_PARSE), $whitespaceFilter, $commentFilter);
        if ($before !== $after)
        {
            throw new RuntimeException("Formatting check failed: parsed output doesn't match input");
        }
        return $out;
    }

    private function filter(array $tokens, TokenFilter ...$filters): array
    {
        foreach ($filters as $filter)
        {
            $tokens = array_filter($tokens, $filter);
        }
        return $tokens;
    }

    private function strip(array $tokens, TokenFilter ...$filters): array
    {
        $tokens = array_values($this->filter($tokens, ...$filters));
        foreach ($tokens as &$token)
        {
            if (is_array($token))
            {
                unset($token[2]);
                if (in_array($token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]))
                {
                    $token[1] = rtrim($token[1]);
                }
            }
        }
        unset($token);
        return $tokens;
    }
}
