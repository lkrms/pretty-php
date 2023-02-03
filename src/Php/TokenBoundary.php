<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concept\Enumeration;

final class TokenBoundary extends Enumeration
{
    public const ASSIGNMENT = 1 << 0;
    public const COMPARISON = 1 << 1;

    public const ALL =
        self::ASSIGNMENT
            | self::COMPARISON;

    public const NONE = 0;

    /**
     * @return array<int|string>
     */
    public static function getTokenTypes(int $flags): array
    {
        return [
            ...($flags & self::ASSIGNMENT ? [
                ...TokenType::OPERATOR_ASSIGNMENT,
            ] : []),
            ...($flags & self::COMPARISON ? [
                ...TokenType::OPERATOR_COMPARISON,
            ] : []),
        ];
    }
}
