<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token\Token;

/**
 * Base interface for formatter rules
 */
interface Rule extends Extension
{
    public const BEFORE_RENDER = 'beforeRender';

    public const CALLBACK = 'callback';

    /**
     * Get the priority of a method implemented by the rule
     *
     * Higher priorities (bigger numbers) correspond to later invocation. To
     * suppress calls to the method, return `null`.
     */
    public static function getPriority(string $method): ?int;

    /**
     * @param Token[] $tokens
     */
    public function beforeRender(array $tokens): void;
}
