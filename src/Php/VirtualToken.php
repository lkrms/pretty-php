<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Facade\Convert;

class VirtualToken extends Token
{
    /**
     * @param array<string|array{0:int,1:string,2:int}> $plainTokens
     * @param Token[] $tokens
     * @param Token[] $bracketStack
     * @param Token[]|null $nextBracketStack
     */
    public function __construct(array &$plainTokens, array &$tokens, Token $insertAt, array $bracketStack, Formatter $formatter, ?array $nextBracketStack = null)
    {
        $this->Type = TokenType::T_VIRTUAL;
        $this->Code = '';
        $this->Line = $insertAt->Line;

        $plainTokens[] = '';
        end($plainTokens);
        $index = key($plainTokens);
        Convert::arraySpliceAtKey($tokens, $insertAt->Index, 0, [$index => $this]);
        $this->insertAt($insertAt);
        if ($nextBracketStack) {
            $insertAt->BracketStack = $nextBracketStack;
        }

        $this->Index        = $index;
        $this->BracketStack = $bracketStack;
        $this->TypeName     = TokenType::class . '::T_VIRTUAL';
        $this->Formatter    = $formatter;
    }
}
