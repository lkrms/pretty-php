<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * @api
 *
 * @phpstan-require-implements Extension
 */
trait ExtensionTrait
{
    private Formatter $Formatter;
    private TokenTypeIndex $Idx;

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
