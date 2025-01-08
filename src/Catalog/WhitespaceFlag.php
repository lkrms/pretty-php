<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token whitespace flags
 *
 * @api
 */
interface WhitespaceFlag
{
    public const SPACE = 1;
    public const LINE = 2;
    public const BLANK = 4;
    public const NO_SPACE = 64;
    public const NO_LINE = 128;
    public const NO_BLANK = 256;
    public const CRITICAL_SPACE = 4096;
    public const CRITICAL_LINE = 8192;
    public const CRITICAL_BLANK = 16384;
    public const CRITICAL_NO_SPACE = 262144;
    public const CRITICAL_NO_LINE = 524288;
    public const CRITICAL_NO_BLANK = 1048576;
    public const SPACE_BEFORE = 1;
    public const LINE_BEFORE = 2;
    public const BLANK_BEFORE = 4;
    public const SPACE_AFTER = 8;
    public const LINE_AFTER = 16;
    public const BLANK_AFTER = 32;
    public const NO_SPACE_BEFORE = 64;
    public const NO_LINE_BEFORE = 128;
    public const NO_BLANK_BEFORE = 256;
    public const NO_SPACE_AFTER = 512;
    public const NO_LINE_AFTER = 1024;
    public const NO_BLANK_AFTER = 2048;
    public const CRITICAL_SPACE_BEFORE = 4096;
    public const CRITICAL_LINE_BEFORE = 8192;
    public const CRITICAL_BLANK_BEFORE = 16384;
    public const CRITICAL_SPACE_AFTER = 32768;
    public const CRITICAL_LINE_AFTER = 65536;
    public const CRITICAL_BLANK_AFTER = 131072;
    public const CRITICAL_NO_SPACE_BEFORE = 262144;
    public const CRITICAL_NO_LINE_BEFORE = 524288;
    public const CRITICAL_NO_BLANK_BEFORE = 1048576;
    public const CRITICAL_NO_SPACE_AFTER = 2097152;
    public const CRITICAL_NO_LINE_AFTER = 4194304;
    public const CRITICAL_NO_BLANK_AFTER = 8388608;

    public const NONE =
        self::NO_SPACE
        | self::NO_LINE
        | self::NO_BLANK;

    public const NONE_BEFORE =
        self::NO_SPACE_BEFORE
        | self::NO_LINE_BEFORE
        | self::NO_BLANK_BEFORE;

    public const NONE_AFTER =
        self::NO_SPACE_AFTER
        | self::NO_LINE_AFTER
        | self::NO_BLANK_AFTER;

    public const CRITICAL_NONE_BEFORE =
        self::CRITICAL_NO_SPACE_BEFORE
        | self::CRITICAL_NO_LINE_BEFORE
        | self::CRITICAL_NO_BLANK_BEFORE;
}
