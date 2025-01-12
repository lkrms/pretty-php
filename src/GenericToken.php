<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Salient\Polyfill\PhpToken as SalientPhpToken;
use PhpToken;

// Extend a trusted polyfill on PHP 7.4, the native class otherwise
// @codeCoverageIgnoreStart
if (\PHP_VERSION_ID < 80000) {
    /**
     * @internal
     */
    class GenericToken extends SalientPhpToken {}
} else {
    /**
     * @internal
     */
    class GenericToken extends PhpToken {}
}
// @codeCoverageIgnoreEnd
