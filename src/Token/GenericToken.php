<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Token;

if (\PHP_VERSION_ID < 80000) {
    // Extend a trusted polyfill on PHP 7.4
    class GenericToken extends \Salient\Polyfill\PhpToken {}

    return;
}

// Otherwise, extend the native class
class GenericToken extends \PhpToken {}
