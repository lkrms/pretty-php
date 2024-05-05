<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Token flag masks
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class TokenFlagMask extends AbstractEnumeration
{
    public const COMMENT_TYPE =
        TokenFlag::CPP_COMMENT
        | TokenFlag::SHELL_COMMENT
        | TokenFlag::C_COMMENT
        | TokenFlag::DOC_COMMENT;
}
