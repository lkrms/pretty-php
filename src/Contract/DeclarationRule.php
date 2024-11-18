<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Token;

/**
 * @api
 */
interface DeclarationRule extends Rule
{
    public const PROCESS_DECLARATIONS = 'processDeclarations';

    /**
     * Apply the rule to the given declarations
     *
     * An array of declaration tokens is passed to this method once per
     * document. The following values are applied to each token's
     * {@see Token::$Data} array:
     *
     * - {@see TokenData::NAMED_DECLARATION_PARTS}
     * - {@see TokenData::NAMED_DECLARATION_TYPE}
     *
     * @param array<int,Token> $declarations
     */
    public function processDeclarations(array $declarations): void;
}
