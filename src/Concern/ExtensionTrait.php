<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;

/**
 * Implements the extension interface
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
    public function reset(): void {}
}
