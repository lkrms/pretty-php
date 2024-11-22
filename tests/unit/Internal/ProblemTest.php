<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Internal;

use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Internal\Problem;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Parser;
use Salient\Utility\Arr;
use Salient\Utility\File;

final class ProblemTest extends TestCase
{
    public function testConstructor(): void
    {
        $parser = new Parser($formatter = new Formatter());

        $filters = array_map(
            function (string $filter) use ($formatter): Filter {
                $_filter = new $filter($formatter);
                $_filter->boot();
                return $_filter;
            },
            Formatter::DEFAULT_FILTERS,
        );

        $file = self::getFixturesPath(__CLASS__) . '/valid.php';

        $tokens = $parser->parse(File::getContents($file), ...$filters)->Tokens;
        $this->assertNotNull($first = $tokens[0] ?? null);
        $this->assertNotNull($second = $tokens[1] ?? null);
        $this->assertNotNull($last = Arr::last($tokens));
        $this->assertSame(-1, $first->OutputLine);
        $this->assertSame(-1, $second->OutputLine);
        $last->OutputLine = 7;
        $last->OutputColumn = 2;

        $this->assertSame('T_OPEN_TAG', $value = $first->getTokenName());
        $format = '%s should not be here';
        $problem = new Problem($format, $file, $first, null, $value);
        $this->assertSame($format, $problem->Format);
        $this->assertSame([$value], $problem->Values);
        $this->assertSame($file, $problem->Filename);
        $this->assertSame($first, $problem->Start);
        $this->assertNull($problem->End);
        $this->assertSame(
            sprintf($format . ': %s:1:1', $value, $file),
            (string) $problem,
        );
        $first->OutputLine = 2;
        $first->OutputColumn = 4;
        $this->assertSame(
            sprintf($format . ': %s:2:4', $value, $file),
            (string) $problem,
        );

        $format = 'Block does not comply';
        $problem = new Problem($format, $file, $second, $last);
        $this->assertSame(
            sprintf($format . ': %s:2:1,%s:5:1', $file, $file),
            (string) $problem,
        );
        $second->OutputLine = 4;
        $second->OutputColumn = 2;
        $this->assertSame(
            sprintf($format . ': %s:4:2,%s:7:2', $file, $file),
            (string) $problem,
        );
    }
}
