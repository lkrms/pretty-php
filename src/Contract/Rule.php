<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token;

/**
 * @api
 */
interface Rule extends Extension
{
    public const BEFORE_RENDER = 'beforeRender';
    public const CALLBACK = 'callback';

    /**
     * Get the priority of the given method, or null if it should not be called
     *
     * Higher priorities (bigger numbers) correspond to later invocation.
     */
    public static function getPriority(string $method): ?int;

    /**
     * Apply the rule to the given tokens
     *
     * All tokens are passed to this method once per document.
     *
     * @param Token[] $tokens
     */
    public function beforeRender(array $tokens): void;
}
