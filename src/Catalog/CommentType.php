<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Comment types
 *
 * @api
 *
 * @extends Enumeration<string>
 */
final class CommentType extends Enumeration
{
    public const C = '/*';

    public const CPP = '//';

    public const SHELL = '#';

    public const DOC_COMMENT = '/**';
}
