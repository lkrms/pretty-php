<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token sub-ids
 *
 * @api
 */
interface TokenSubId
{
    public const COLON_TERNARY = 0;
    public const COLON_ALT_SYNTAX = 1;
    public const COLON_NAMED_ARGUMENT = 2;
    public const COLON_SWITCH_CASE = 3;
    public const COLON_LABEL = 4;
    public const COLON_RETURN_TYPE = 5;
    public const COLON_ENUM_TYPE = 6;
    public const QUESTION_TERNARY = 7;
    public const QUESTION_NULLABLE = 8;
}
