<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Salient\Core\AbstractEnumeration;

/**
 * Comment types
 *
 * @api
 *
 * @extends AbstractEnumeration<string>
 */
final class CommentType extends AbstractEnumeration
{
    public const C = '/*';

    public const CPP = '//';

    public const SHELL = '#';

    public const DOC_COMMENT = '/**';
}
