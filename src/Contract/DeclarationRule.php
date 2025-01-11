<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Token;

/**
 * @api
 */
interface DeclarationRule extends Rule
{
    public const PROCESS_DECLARATIONS = 'processDeclarations';

    /**
     * Get declaration types the rule is interested in
     *
     * Declarations of these types are passed to
     * {@see DeclarationRule::processDeclarations()} during formatting.
     *
     * Returns an index of declaration types, optionally derived from the
     * complete one provided, or `['*']` for all declarations.
     *
     * @param array<int,true> $all
     * @return array<int,bool>|array{'*'}
     */
    public static function getDeclarationTypes(array $all): array;

    /**
     * Check if the rule requires declarations to be given in document order
     */
    public static function needsSortedDeclarations(): bool;

    /**
     * Apply the rule to the given declarations
     *
     * An array of declaration tokens is passed to this method once per
     * document. The following values are applied to each token's
     * {@see Token::$Data} array:
     *
     * - {@see Data::DECLARATION_PARTS}
     * - {@see Data::DECLARATION_TYPE}
     * - {@see Data::PROPERTY_HOOKS} (if the declaration is a property or
     *   promoted constructor parameter)
     *
     * @param array<int,Token> $declarations
     */
    public function processDeclarations(array $declarations): void;
}
