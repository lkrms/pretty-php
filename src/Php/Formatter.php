<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Filter\RemoveCommentTokens;
use Lkrms\Pretty\Php\Filter\RemoveEmptyTokens;
use Lkrms\Pretty\Php\Filter\RemoveWhitespaceTokens;
use Lkrms\Pretty\Php\Filter\StripHeredocIndents;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use Lkrms\Pretty\Php\Rule\AddHangingIndentation;
use Lkrms\Pretty\Php\Rule\AddIndentation;
use Lkrms\Pretty\Php\Rule\BracePosition;
use Lkrms\Pretty\Php\Rule\BreakAfterSeparators;
use Lkrms\Pretty\Php\Rule\CommaCommaComma;
use Lkrms\Pretty\Php\Rule\PlaceComments;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\PrettyException;
use Lkrms\Pretty\WhitespaceType;
use ParseError;

/**
 * @property-read string $Tab
 * @property-read string[] $Rules
 * @property-read array<string|array{0:int,1:string,2:int}>|null $PlainTokens
 * @property-read Token[]|null $Tokens
 */
final class Formatter implements IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Tab;

    /**
     * @var string[]
     */
    protected $Rules = [
        BreakAfterSeparators::class,
        BracePosition::class,
        SpaceOperators::class,
        CommaCommaComma::class,
        PlaceComments::class,
        PreserveNewlines::class,
        AddIndentation::class,
        AddHangingIndentation::class,
        ReindentHeredocs::class,
        AddEssentialWhitespace::class,
    ];

    /**
     * @var array<string|array{0:int,1:string,2:int}>|null
     */
    protected $PlainTokens;

    /**
     * @var Token[]|null
     */
    protected $Tokens;

    /**
     * @var TokenFilter[]
     */
    private $Filters;

    /**
     * @var TokenFilter[]
     */
    private $ComparisonFilters;

    public function __construct(string $tab = "    ")
    {
        $this->Tab = $tab;

        $this->Filters = [
            new RemoveWhitespaceTokens(),
            new StripHeredocIndents(),
        ];
        $this->ComparisonFilters = [
            ...$this->Filters,
            new RemoveCommentTokens(),
            new RemoveEmptyTokens(),
        ];
    }

    public function format(string $code): string
    {
        [$this->PlainTokens, $this->Tokens] = [token_get_all($code, TOKEN_PARSE), []];

        $bracketStack = [];
        foreach ($this->filter($this->PlainTokens, ...$this->Filters) as $index => $plainToken)
        {
            $this->Tokens[$index] = $token = new Token(
                $index,
                $plainToken,
                end($this->Tokens) ?: null,
                $bracketStack,
                $this
            );

            if ($token->isOpenBracket())
            {
                array_push($bracketStack, $token);
            }

            if ($token->isCloseBracket())
            {
                $opener = array_pop($bracketStack);
                $opener->ClosedBy = $token;
                $token->OpenedBy  = $opener;
            }
        }

        if (isset($token))
        {
            $token->WhitespaceAfter |= WhitespaceType::LINE;
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

        try
        {
            $tokensOut = token_get_all($out, TOKEN_PARSE);
        }
        catch (ParseError $ex)
        {
            throw new PrettyException("Formatting check failed: output cannot be parsed", $out, $this->Tokens, $ex);
        }

        $before = $this->strip($this->PlainTokens, ...$this->ComparisonFilters);
        $after  = $this->strip($tokensOut, ...$this->ComparisonFilters);
        if ($before !== $after)
        {
            throw new PrettyException("Formatting check failed: parsed output doesn't match input", $out, $this->Tokens);
        }

        return $out;
    }

    private function filter(array $tokens, TokenFilter ...$filters): array
    {
        foreach ($filters as $filter)
        {
            foreach ($tokens as $key => & $token)
            {
                if (!$filter($token))
                {
                    unset($tokens[$key]);
                }
            }
            unset($token);
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
