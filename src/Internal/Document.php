<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;

/**
 * @internal
 */
final class Document
{
    /** @var list<Token> */
    public array $Tokens;
    /** @var array<int,array<int,Token>> */
    public array $TokensById;
    /** @var array<int,Token> */
    public array $Statements;
    /** @var array<int,Token> */
    public array $Declarations;
    /** @var array<int,array<int,Token>> */
    public array $DeclarationsByType;

    /**
     * @param list<Token> $tokens
     * @param array<int,array<int,Token>> $tokensById
     * @param array<int,Token> $statements
     * @param array<int,Token> $declarations
     * @param array<int,array<int,Token>> $declarationsByType
     */
    public function __construct(
        array $tokens = [],
        array $tokensById = [],
        array $statements = [],
        array $declarations = [],
        array $declarationsByType = []
    ) {
        $this->Tokens = $tokens;
        $this->TokensById = $tokensById;
        $this->Statements = $statements;
        $this->Declarations = $declarations;
        $this->DeclarationsByType = $declarationsByType;
    }
}
