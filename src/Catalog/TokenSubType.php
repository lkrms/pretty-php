<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractReflectiveEnumeration;

/**
 * Token sub-types
 *
 * @api
 *
 * @extends AbstractReflectiveEnumeration<int>
 */
final class TokenSubType extends AbstractReflectiveEnumeration
{
    public const COLON_TERNARY_OPERATOR = 0;
    public const COLON_ALT_SYNTAX_DELIMITER = 1;
    public const COLON_LABEL_DELIMITER = 2;
    public const COLON_SWITCH_CASE_DELIMITER = 3;
    public const COLON_RETURN_TYPE_DELIMITER = 4;
    public const COLON_NAMED_ARGUMENT_DELIMITER = 5;
    public const COLON_BACKED_ENUM_TYPE_DELIMITER = 6;
    public const QUESTION_TERNARY_OPERATOR = 7;
    public const QUESTION_NULLABLE = 8;
    public const USE_IMPORT = 9;
    public const USE_TRAIT = 10;
    public const USE_VARIABLES = 11;
}
