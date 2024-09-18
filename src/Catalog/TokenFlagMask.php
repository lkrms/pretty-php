<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

/**
 * Token flag masks
 *
 * @api
 */
interface TokenFlagMask
{
    public const COMMENT_TYPE =
        TokenFlag::CPP_COMMENT
        | TokenFlag::SHELL_COMMENT
        | TokenFlag::C_COMMENT
        | TokenFlag::DOC_COMMENT;
}
