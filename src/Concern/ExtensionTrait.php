<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;

/**
 * @phpstan-require-implements Extension
 */
trait ExtensionTrait
{
    protected Formatter $Formatter;
    protected TokenTypeIndex $TypeIndex;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
        $this->TypeIndex = $formatter->TokenTypeIndex;
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
