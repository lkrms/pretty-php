<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\Formatter;

trait RuleTrait
{
    /**
     * @var Formatter
     */
    protected $Formatter;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
    }

    public function getPriority(string $method): ?int
    {
        return null;
    }

    public function destroy(): void
    {
        unset($this->Formatter);
    }
}
