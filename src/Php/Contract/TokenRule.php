<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;

interface TokenRule
{
    public function __construct(Formatter $formatter);

    public function __invoke(Token $token, int $stage): void;

    /**
     * Return an array that maps stages to (optional) priorities
     *
     * @return array<int,int|null>
     */
    public function getStages(): array;

    public function afterTokenLoop(): void;

    public function beforeRender(): void;

    public function clear(): void;
}
