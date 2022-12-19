<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concept;

use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Formatter;

abstract class AbstractTokenRule implements TokenRule
{
    /**
     * @var Formatter
     */
    protected $Formatter;

    public function __construct(Formatter $formatter)
    {
        $this->Formatter = $formatter;
    }

    public function getStages(): array
    {
        return [
            1                            => null,
            self::STAGE_AFTER_TOKEN_LOOP => null,
            self::STAGE_BEFORE_RENDER    => null,
        ];
    }

    public function afterTokenLoop(): void
    {
    }

    public function beforeRender(): void
    {
    }

    public function clear(): void
    {
    }
}
