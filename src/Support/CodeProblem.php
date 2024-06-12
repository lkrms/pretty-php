<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\PrettyPHP\Token\Token;
use Salient\Utility\Str;
use Stringable;

/**
 * A non-critical problem detected in formatted code
 */
final class CodeProblem implements Stringable
{
    /**
     * An sprintf() format string describing the problem
     *
     * @readonly
     */
    public string $Format;

    /**
     * Values for the sprintf() format string
     *
     * @readonly
     * @var mixed[]
     */
    public array $Values;

    /**
     * The name of the file with the problem
     *
     * @readonly
     */
    public ?string $Filename;

    /**
     * The start of the range of tokens with the problem
     *
     * @readonly
     */
    public Token $Start;

    /**
     * The end of the range of tokens with the problem
     *
     * May be `null` if the problem only affects one token.
     *
     * @readonly
     */
    public ?Token $End;

    /**
     * @param mixed ...$values
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
     * @internal
     */
    public function __toString(): string
    {
        $format = ': %s:%d:%d';
        $locations[] = $this->Start;
        if ($this->End && $this->End !== $this->Start) {
            $format .= ',%1$s:%d:%d';
            $locations[] = $this->End;
        }

        // Use lines and columns from `OutputLine` and `OutputColumn` if they
        // are never `null`, otherwise fall back to `line` and `column`
        $out = [];
        foreach ($locations as $loc) {
            $in[] = $loc->line;
            $in[] = $loc->column;
            if ($out === null) {
                continue;
            }
            if ($loc->OutputLine === null || $loc->OutputColumn === null) {
                $out = null;
                continue;
            }
            $out[] = $loc->OutputLine;
            $out[] = $loc->OutputColumn;
        }

        return sprintf($this->Format, ...$this->Values)
            . sprintf($format, $this->Filename ?? '<input>', ...($out ?? $in));
    }
}
