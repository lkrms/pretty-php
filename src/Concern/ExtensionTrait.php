<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Concern;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Formatter;

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
        $this->Formatter = $formatter;
        $this->TypeIndex = $formatter->TokenTypeIndex;
    }

    public function setFormatter(Formatter $formatter): void
    {
        $this->Formatter = $formatter;
        $this->TypeIndex = $formatter->TokenTypeIndex;
    }

    public function reset(): void {}
}
