<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Internal;

use Lkrms\PrettyPHP\Token;
use Salient\Utility\Str;
use Stringable;

/**
 * @internal
 */
final class Problem implements Stringable
{
    public string $Format;
    /** @var array<int|float|string|bool|null> */
    public array $Values;
    public ?string $Filename;
    public Token $Start;
    public ?Token $End;

    /**
     * @param int|float|string|bool|null ...$values
     */
    public function __construct(
        string $format,
        ?string $filename,
        Token $start,
        ?Token $end = null,
        ...$values
    ) {
        $this->Format = $format;
        $this->Values = $values;
        $this->Filename = Str::coalesce($filename, null);
        $this->Start = $start;
        $this->End = $end;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $format = ': %s:%d:%d';
        $locations[] = $this->Start;
        if ($this->End && $this->End !== $this->Start) {
            $format .= ' -> %1$s:%d:%d';
            $locations[] = $this->End;
        }

        // Use lines and columns from `OutputLine` and `OutputColumn` if none
        // are `-1`, otherwise fall back to `line` and `column`
        foreach ($locations as $location) {
            $values[] = $location->OutputLine;
            $values[] = $location->OutputColumn;
        }

        if (in_array(-1, $values, true)) {
            $values = [];
            foreach ($locations as $location) {
                $values[] = $location->line;
                $values[] = $location->column;
            }
        }

        return sprintf($this->Format, ...$this->Values)
            . sprintf($format, $this->Filename ?? '<input>', ...$values);
    }
}
