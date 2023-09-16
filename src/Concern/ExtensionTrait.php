<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;

/**
 * Implements Lkrms\PrettyPHP\Contract\Extension for use by filters and rules
 *
 */
trait ExtensionTrait
{
    /**
     * @var Formatter
     */
    protected $Formatter;

    /**
     * @var TokenTypeIndex
     */
    protected $TypeIndex;

    public function __construct(Formatter $formatter)
    {
        $this->setFormatter($formatter);
    }

    public function setFormatter(Formatter $formatter): void
    {
        $this->Formatter = $formatter;
        $this->TypeIndex = &$formatter->TokenTypeIndex;
    }

    public function reset(): void {}
}
