<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Command;

use Lkrms\Cli\CliApplication;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Target\MockTarget;
use Lkrms\Exception\Contract\ExceptionInterface;
use Lkrms\Facade\Console;
use Lkrms\PrettyPHP\Command\FormatPhp;
use Lkrms\Utility\File;
use Generator;

final class FormatPhpTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    private static string $BasePath;

    public static function setUpBeforeClass(): void
    {
        self::$BasePath = File::createTempDir();
    }

    public static function tearDownAfterClass(): void
    {
        File::pruneDir(self::$BasePath);
        rmdir(self::$BasePath);
    }

    /**
     * @dataProvider drupalProvider
     */
    public function testDrupal(string $file): void
    {
        $this->makePresetAssertions('drupal', $file);
    }

    /**
     * @return Generator<string,array{string}>
     */
    public static function drupalProvider(): Generator
    {
        yield from self::getInputFiles('preset/drupal');
    }

    /**
     * @dataProvider symfonyProvider
     * @requires PHP >= 8.0
     */
    public function testSymfony(string $file): void
    {
        $this->makePresetAssertions('symfony', $file);
    }

    /**
     * @return Generator<string,array{string}>
     */
    public static function symfonyProvider(): Generator
    {
        yield from self::getInputFiles('preset/symfony');
    }

    /**
     * @dataProvider wordpressProvider
     */
    public function testWordpress(string $file): void
    {
        $this->makePresetAssertions('wordpress', $file);
    }

    /**
     * @return Generator<string,array{string}>
     */
    public static function wordpressProvider(): Generator
    {
        yield from self::getInputFiles('preset/wordpress');
    }

    public function testMultipleConfigFiles(): void
    {
        $dir = $this->getFixturesPath(__CLASS__) . '/multiple-config-files';
        $this->assertCommandProduces(null, null, ['--', $dir], 2);
    }

    private function makePresetAssertions(string $preset, string $file): void
    {
        $input = File::getContents($file);
        $expected = File::getContents(substr($file, 0, -3) . '.out');
        $this->assertCommandProduces($expected, $input, ['--preset', $preset]);
    }

    /**
     * @param string[] $args
     */
    private function assertCommandProduces(
        ?string $output,
        ?string $input,
        array $args = [],
        int $exitStatus = 0
    ): void {
        $target = new MockTarget();
        Console::registerTarget($target, Levels::ALL_EXCEPT_DEBUG);

        $app = new CliApplication(self::$BasePath);
        $formatPhp = $app->get(FormatPhp::class);

        try {
            if ($input === null) {
                $this->expectOutputString((string) $output);
                $this->assertSame($exitStatus, $formatPhp(...$args));
                return;
            }

            $temp = $app->getTempPath();
            $src1 = tempnam($temp, 'src');
            $src2 = tempnam($temp, 'src');
            File::putContents($src1, $input);
            File::putContents($src2, (string) $output);

            $output .= $output;
            array_push($args, '--no-config', '-o', '-', '--');

            $this->expectOutputString($output);
            $this->assertSame($exitStatus, $formatPhp(...[...$args, $src1]));
            $this->assertSame($exitStatus, $formatPhp(...[...$args, $src2]));
        } catch (ExceptionInterface $ex) {
            if (!$exitStatus) {
                throw $ex;
            }
            $this->assertSame($exitStatus, $ex->getExitStatus());
        } finally {
            $app->unload();
            Console::deregisterTarget($target);
        }
    }

    /**
     * @return Generator<string,array{string}>
     */
    private static function getInputFiles(string $source): Generator
    {
        $dir = self::getFixturesPath(__CLASS__) . "/$source";
        $offset = strlen($dir) + 1;
        $files = File::find()
                     ->in($dir)
                     ->include('/\.in$/');

        foreach ($files as $file) {
            $path = substr((string) $file, $offset);
            yield $path => [(string) $file];
        }
    }
}
