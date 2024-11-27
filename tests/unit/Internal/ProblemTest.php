<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Internal;

use Lkrms\PrettyPHP\Filter\CollectColumn;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
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
        $file = self::getFixturesPath(__CLASS__) . '/valid.php';
        $code = File::getContents($file);

        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $tokens = $parser->parse(
            $code,
            new CollectColumn($formatter),
            new RemoveWhitespace($formatter),
        )->Tokens;

        $this->assertNotEmpty($tokens);
        $this->assertGreaterThan(2, count($tokens));
        $first = $tokens[0];
        $second = $tokens[1];
        $last = Arr::last($tokens);
        $this->assertSame(-1, $first->OutputLine);
        $this->assertSame(-1, $second->OutputLine);
        $last->OutputLine = 7;
        $last->OutputColumn = 2;

        $problem = new Problem('%s should not be here', $file, $first, null, $first->getTokenName());
        $this->assertSame('%s should not be here', $problem->Format);
        $this->assertSame(['T_OPEN_TAG'], $problem->Values);
        $this->assertSame($file, $problem->Filename);
        $this->assertSame($first, $problem->Start);
        $this->assertNull($problem->End);
        $this->assertSame(
            sprintf('T_OPEN_TAG should not be here: %s:1:1', $file),
            (string) $problem,
        );
        $first->OutputLine = 2;
        $first->OutputColumn = 4;
        $this->assertSame(
            sprintf('T_OPEN_TAG should not be here: %s:2:4', $file),
            (string) $problem,
        );

        $problem = new Problem('Block does not comply', $file, $second, $last);
        $this->assertSame(
            sprintf('Block does not comply: %s:2:1 -> %s:5:1', $file, $file),
            (string) $problem,
        );
        $second->OutputLine = 4;
        $second->OutputColumn = 2;
        $this->assertSame(
            sprintf('Block does not comply: %s:4:2 -> %s:7:2', $file, $file),
            (string) $problem,
        );
    }
}
