<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Named declaration types
 *
 * @api
 */
interface DeclarationType
{
    public const _CASE = 1;
    public const _CLASS = 2;
    public const _CONST = 4;
    public const _DECLARE = 8;
    public const _ENUM = 16;
    public const _FUNCTION = 32;
    public const _INTERFACE = 64;
    public const _NAMESPACE = 128;
    public const _TRAIT = 256;
    public const _USE = 512;
    public const PROPERTY = 1024;
    public const HOOK = 2048;

    /**
     * Promoted constructor parameter
     */
    public const PARAM = 4096 | self::PROPERTY;

    /**
     * "use const" import statement
     */
    public const USE_CONST = self::_USE | self::_CONST;

    /**
     * "use function" import statement
     */
    public const USE_FUNCTION = self::_USE | self::_FUNCTION;

    /**
     * Trait insertion statement
     */
    public const USE_TRAIT = self::_USE | self::_TRAIT;
}
