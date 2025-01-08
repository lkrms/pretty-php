<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\AbstractTokenIndex;
use Lkrms\PrettyPHP\Formatter;

/**
 * @api
 *
 * @phpstan-require-implements Extension
 */
trait ExtensionTrait
{
    private Formatter $Formatter;
    private AbstractTokenIndex $Idx;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->Idx = $formatter->TokenIndex;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void {}

    /**
     * @inheritDoc
     */
    public function reset(): void {}

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return [];
    }
}
