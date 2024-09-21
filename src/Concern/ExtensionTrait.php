<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;

/**
 * @api
 *
 * @phpstan-require-implements Extension
 */
trait ExtensionTrait
{
    protected Formatter $Formatter;
    protected TokenTypeIndex $Idx;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->Idx = $formatter->TokenTypeIndex;
    }

    /**
     * @inheritDoc
     */
    public function boot(): void {}

    /**
     * @inheritDoc
     */
    public function reset(): void {}
}
