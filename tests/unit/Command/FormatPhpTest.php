<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Command;

use Lkrms\PrettyPHP\Command\FormatPhp;
use Salient\Cli\CliApplication;
use Salient\Console\Target\MockTarget;
use Salient\Contract\Core\ExceptionInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Generator;

/**
 * @backupGlobals enabled
 */
final class FormatPhpTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    private const SYNOPSIS = <<<'EOF'

pretty-php [-1OLNSnMvq] [-I <regex>] [-X <regex>] [-P[<regex>]] [-i <rule>,...]
    [-r <rule>,...] [-h <level>] [-m <order>] [--psr12] [-c <file>]
    [--no-config] [-o <file>,...] [--diff[=<type>]] [--check] [--print-config]
    [--] [<path>...]

See 'pretty-php --help' for more information.
EOF;

    private static string $FixturesPath;

    private static string $BasePath;

    private CliApplication $App;

    private MockTarget $ConsoleTarget;

    public static function setUpBeforeClass(): void
    {
        self::$FixturesPath = self::getFixturesPath(__CLASS__);
        self::$BasePath = File::createTempDir();
    }

    protected function setUp(): void
    {
        $this->ConsoleTarget = new MockTarget(null, true, true, false);
        Console::registerTarget($this->ConsoleTarget, LevelGroup::ALL_EXCEPT_DEBUG);

        $_SERVER['SCRIPT_FILENAME'] = 'pretty-php';

        $this->App = (new CliApplication(self::$BasePath))
            ->oneCommand(FormatPhp::class);
    }

    protected function tearDown(): void
    {
        $this->App->unload();

        Console::deregisterTarget($this->ConsoleTarget);
        Console::unload();
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
     * @requires PHP >= 8.1
     * @dataProvider laravelProvider
     */
    public function testLaravel(string $file): void
    {
        $this->makePresetAssertions('laravel', $file);
    }

    /**
     * @return Generator<string,array{string}>
     */
    public static function laravelProvider(): Generator
    {
        yield from self::getInputFiles('preset/laravel');
    }

    /**
     * @requires PHP >= 8.0
     * @dataProvider symfonyProvider
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

    private function makePresetAssertions(string $preset, string $file): void
    {
        $input = File::getContents($file);
        $expected = File::getContents(substr($file, 0, -3) . '.out');
        $this->assertCommandProduces($expected, $input, ['--preset', $preset]);
    }

    /**
     * @dataProvider runProvider
     *
     * @param string[] $args
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|null $messages
     */
    public function testRun(
        int $exitStatus,
        ?string $output,
        ?string $outputFile,
        ?string $inputFile,
        array $args,
        ?array $messages = null
    ): void {
        $dir = File::createTempDir($this->App->getTempPath());
        $file = $dir . '/code.php';
        File::createDir($dir . '/.git');
        File::chdir($dir);
        $this->App->setWorkingDirectory();

        if ($inputFile !== null) {
            File::putContents($file, $inputFile);
        }

        $this->assertCommandProduces($output, null, $args, $exitStatus, $messages);

        if ($outputFile !== null) {
            $this->assertSame($outputFile, File::getContents($file));
        }
    }

    /**
     * @return array<string,array{int,string|null,string|null,string|null,string[],5?:array<array{Level::*,string,2?:array<string,mixed>}>|null}>
     */
    public static function runProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);
        $noSortImportsFile = File::getContents(
            $dir . '/no-sort-imports/Foo.php',
        );

        return [
            'no-sort-imports' => [
                0,
                '',
                $noSortImportsFile,
                $noSortImportsFile,
                ['-M', '.'],
                [[Level::INFO, ' // Formatted 1 file successfully']],
            ],
        ];
    }

    /**
     * @dataProvider directoriesProvider
     *
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|string|null $message
     */
    public function testDirectories(
        int $exitStatus,
        $message,
        string $dir,
        bool $chdir = false,
        ?string $output = null,
        string ...$args
    ): void {
        if (is_array($message)) {
            $messages = $message;
        } elseif (!$exitStatus) {
            $messages = $message === null
                ? []
                : [[Level::INFO, $message]];
        } elseif ($exitStatus === 2) {
            $messages = [[Level::ERROR, ' !! InvalidConfigurationException: ' . $message]];
        } else {
            $messages = [[Level::ERROR, (string) $message]];
        }
        if ($chdir) {
            File::chdir(self::$FixturesPath . $dir);
            $this->App->setWorkingDirectory();
            $this->assertCommandProduces($output, null, $args, $exitStatus, $messages);
            return;
        }
        $dir = self::$FixturesPath . $dir;
        $this->assertCommandProduces($output, null, [...$args, '--', $dir], $exitStatus, $messages);
    }

    /**
     * @return array<string,array{int,array<array{Level::*,string,2?:array<string,mixed>}>|string|null,string,3?:bool,4?:string|null,...}>
     */
    public static function directoriesProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            'empty' => [
                0,
                ' // Formatted 0 files successfully',
                '/empty',
            ],
            'empty config' => [
                0,
                ' // Formatted 1 file successfully',
                '/empty-config',
            ],
            'empty config in cwd' => [
                0,
                ' // Formatted 1 file successfully',
                '/empty-config',
                true,
            ],
            'multiple config files' => [
                2,
                'Too many configuration files: ',
                '/multiple-config-files'
            ],
            'multiple config files in cwd' => [
                2,
                'Too many configuration files: ./.prettyphp ./prettyphp.json',
                '/multiple-config-files',
                true,
            ],
            'operators first and last' => [
                2,
                'operatorsFirst and operatorsLast cannot both be given in ',
                '/operators-first-and-last',
            ],
            'operators first and last in cwd' => [
                2,
                'operatorsFirst and operatorsLast cannot both be given in ./.prettyphp',
                '/operators-first-and-last',
                true,
            ],
            'no-sort-imports' => [
                0,
                ' // Formatted 1 file successfully',
                '/no-sort-imports',
            ],
            'no-sort-imports in cwd' => [
                0,
                ' // Formatted 1 file successfully',
                '/no-sort-imports',
                true,
            ],
            'invalid sort-imports #1' => [
                2,
                'sortImportsBy and noSortImports/disable=sort-imports cannot both be given in ',
                '/invalid-sort-imports-1',
            ],
            'invalid sort-imports #1 in cwd' => [
                2,
                'sortImportsBy and noSortImports/disable=sort-imports cannot both be given in ./.prettyphp',
                '/invalid-sort-imports-1',
                true,
            ],
            'invalid sort-imports #2' => [
                2,
                'sortImportsBy and noSortImports/disable=sort-imports cannot both be given in ',
                '/invalid-sort-imports-2',
            ],
            'invalid sort-imports #2 in cwd' => [
                2,
                'sortImportsBy and noSortImports/disable=sort-imports cannot both be given in ./.prettyphp',
                '/invalid-sort-imports-2',
                true,
            ],
            'empty config + --check' => [
                0,
                ' -> 1 file would be left unchanged',
                '/empty-config',
                false,
                null,
                '--check',
            ],
            'empty config + --check in cwd' => [
                0,
                ' -> 1 file would be left unchanged',
                '/empty-config',
                true,
                null,
                '--check',
            ],
            'unformatted + --check' => [
                8,
                ' !! Input requires formatting',
                '/unformatted',
                false,
                null,
                '--check',
            ],
            'unformatted + --check in cwd' => [
                8,
                ' !! Input requires formatting',
                '/unformatted',
                true,
                null,
                '--check',
            ],
            'empty config + --diff' => [
                0,
                ' -> 1 file would be left unchanged',
                '/empty-config',
                false,
                null,
                '--diff',
            ],
            'empty config + --diff in cwd' => [
                0,
                ' -> 1 file would be left unchanged',
                '/empty-config',
                true,
                null,
                '--diff',
            ],
            'unformatted + --diff' => [
                8,
                [
                    [Level::INFO, " -> Would replace $dir/unformatted/Foo.php"],
                    [Level::INFO, ''],
                    [Level::INFO, ' -> 1 of 2 files would be replaced'],
                ],
                '/unformatted',
                false,
                self::normaliseUnifiedDiff(<<<EOF
--- a/$dir/unformatted/Foo.php
+++ b/$dir/unformatted/Foo.php
@@ -11,9 +11,9 @@
 
     public function __construct()
     {
-        \$a = 0;          // Short
-        \$foo = 1;        // Long
-        \$quuux = 2;      // Longer
+        \$a = 0;  // Short
+        \$foo = 1;  // Long
+        \$quuux = 2;  // Longer
         \$this->Bar = 3;  // Longest
     }
 }

EOF),
                '--diff',
            ],
            'unformatted + --diff in cwd' => [
                8,
                [
                    [Level::INFO, ' -> Would replace ./Foo.php'],
                    [Level::INFO, ''],
                    [Level::INFO, ' -> 1 of 2 files would be replaced'],
                ],
                '/unformatted',
                true,
                self::normaliseUnifiedDiff(<<<EOF
--- a/./Foo.php
+++ b/./Foo.php
@@ -11,9 +11,9 @@
 
     public function __construct()
     {
-        \$a = 0;          // Short
-        \$foo = 1;        // Long
-        \$quuux = 2;      // Longer
+        \$a = 0;  // Short
+        \$foo = 1;  // Long
+        \$quuux = 2;  // Longer
         \$this->Bar = 3;  // Longest
     }
 }

EOF),
                '--diff',
            ],
            'invalid syntax' => [
                4,
                [
                    [Level::ERROR, ' !! InvalidSyntaxException:' . \PHP_EOL . "  Formatting failed: $dir/invalid-syntax/invalid.php cannot be parsed"],
                    [Level::ERROR, " !! 1 file with invalid syntax not formatted: $dir/invalid-syntax/invalid.php"],
                    [Level::ERROR, ' !! Formatted 2 files with 1 error'],
                ],
                '/invalid-syntax',
            ],
            'invalid syntax in cwd' => [
                4,
                [
                    [Level::ERROR, ' !! InvalidSyntaxException:' . \PHP_EOL . '  Formatting failed: ./invalid.php cannot be parsed'],
                    [Level::ERROR, ' !! 1 file with invalid syntax not formatted: ./invalid.php'],
                    [Level::ERROR, ' !! Formatted 2 files with 1 error'],
                ],
                '/invalid-syntax',
                true,
            ],
            'operators mixed' => [
                0,
                ' // Formatted 1 file successfully',
                '/operators-mixed',
            ],
            'operators first' => [
                0,
                ' // Formatted 1 file successfully',
                '/operators-first',
            ],
            'operators last' => [
                0,
                ' // Formatted 1 file successfully',
                '/operators-last',
            ],
            'sort imports by depth' => [
                0,
                ' // Formatted 1 file successfully',
                '/sort-imports-by-depth',
            ],
            'sort imports by name' => [
                0,
                ' // Formatted 1 file successfully',
                '/sort-imports-by-name',
            ],
        ];
    }

    /**
     * @dataProvider invalidOptionsProvider
     */
    public function testInvalidOptions(string $message, string ...$args): void
    {
        $this->assertCommandProduces(null, null, $args, 1, [
            [Level::ERROR, 'Error: ' . $message],
            [Level::INFO, self::SYNOPSIS],
        ]);
    }

    /**
     * @return array<string,array{string,...}>
     */
    public static function invalidOptionsProvider(): array
    {
        return [
            'two dashes #1' => [
                '<path> does not accept the same value multiple times',
                '-',
                '-',
            ],
            'two dashes #2' => [
                '<path> does not accept the same value multiple times',
                '--',
                '-',
                '-',
            ],
            'dash and path' => [
                "<path> cannot be '-' when multiple paths are given",
                '-',
                __FILE__,
            ],
            'multiple outputs' => [
                '--output cannot be given multiple times when reading from the standard input',
                '-o',
                __DIR__ . '/does_not_exist',
                '-o',
                __DIR__ . '/does_not_exist_either',
                '-',
            ],
            'tab and space' => [
                '--tab and --space cannot both be given',
                '--tab',
                '--space',
            ],
            'tab=1' => [
                "invalid --tab value '1' (expected one of: 2,4,8)",
                '--tab=1',
            ],
            'space=6' => [
                "invalid --space value '6' (expected one of: 2,4,8)",
                '--space=6',
            ],
            'operators first and last' => [
                '--operators-first and --operators-last cannot both be given',
                '--operators-first',
                '--operators-last',
            ],
            'invalid sort-imports #1' => [
                '--sort-imports-by and --no-sort-imports/--disable=sort-imports cannot both be given',
                '--sort-imports-by',
                'name',
                '--no-sort-imports',
            ],
            'invalid sort-imports #2' => [
                '--sort-imports-by and --no-sort-imports/--disable=sort-imports cannot both be given',
                '--sort-imports-by',
                'name',
                '--disable',
                'sort-imports',
            ],
        ];
    }

    /**
     * @dataProvider configProvider
     * @dataProvider printConfigProvider
     */
    public function testPrintConfig(string $config, string ...$args): void
    {
        $this->assertCommandProduces(
            $config . \PHP_EOL,
            null,
            ['--print-config', ...$args],
            0,
            [],
        );
    }

    /**
     * @return array<string,array{string,...}>
     */
    public static function printConfigProvider(): array
    {
        return [
            'current directory' => [
                <<<'EOF'
{
    "src": [
        "."
    ]
}
EOF,
                '.',
            ],
        ];
    }

    /**
     * @dataProvider configProvider
     * @dataProvider printLoadedConfigProvider
     *
     * @param array{string,string}|string $config
     */
    public function testPrintLoadedConfig($config): void
    {
        $config = (array) $config;
        $config[1] ??= $config[0];
        $dir = File::createTempDir($this->App->getTempPath());
        $file = $dir . '/.prettyphp';
        File::putContents($file, $config[1] . \PHP_EOL);
        $this->assertCommandProduces(
            $config[0] . \PHP_EOL,
            null,
            ['--config', $file, '--print-config'],
            0,
            [],
        );
    }

    /**
     * @return array<string,array{array{string,string}|string}>
     */
    public static function printLoadedConfigProvider(): array
    {
        return [
            'current directory' => [
                [
                    '{}',
                    <<<'EOF'
{
    "src": [
        "."
    ]
}
EOF,
                ],
            ],
            'tabSize only' => [
                [
                    <<<'EOF'
{
    "insertSpaces": true,
    "tabSize": 2
}
EOF,
                    <<<'EOF'
{
    "tabSize": 2
}
EOF,
                ],
            ],
        ];
    }

    /**
     * @return array<string,array{string,...}>
     */
    public static function configProvider(): array
    {
        $data = self::getConfigData();
        foreach ($data as &$args) {
            if (!is_string($args[0])) {
                $args[0] = Json::prettyPrint($args[0]);
            }
        }
        return $data;
    }

    /**
     * @return array<string,array{array<string,mixed>|string,...}>
     */
    private static function getConfigData(): array
    {
        return [
            'empty' => [
                '{}',
            ],
            'tab' => [
                [
                    'insertSpaces' => false,
                    'tabSize' => 4,
                ],
                '--tab',
            ],
            'tab=2' => [
                [
                    'insertSpaces' => false,
                    'tabSize' => 2,
                ],
                '--tab=2',
            ],
            'space' => [
                [
                    'insertSpaces' => true,
                    'tabSize' => 4,
                ],
                '--space',
            ],
            'space=2' => [
                [
                    'insertSpaces' => true,
                    'tabSize' => 2,
                ],
                '--space=2',
            ],
            'preset' => [
                [
                    'preset' => 'laravel',
                ],
                '--preset',
                'laravel',
            ],
            'preset + psr12' => [
                [
                    'psr12' => true,
                    'preset' => 'laravel',
                ],
                '--preset',
                'laravel',
                '--psr12',
            ],
            'preset + tab' => [
                [
                    'preset' => 'laravel',
                ],
                '--preset',
                'laravel',
                '--tab',
            ],
        ];
    }

    public function testDebug(): void
    {
        $file = self::$FixturesPath . '/empty-config/Foo.php';
        $messages = [[Level::INFO, ' // Formatted 1 file successfully']];
        $dir = $this->App->getTempPath() . '/debug';

        $this->assertCommandProduces(null, null, ['--debug', $file], 0, $messages);
        $this->assertDirectoryExists($dir);
        $this->assertDirectoryDoesNotExist("$dir/progress-log");

        $this->assertCommandProduces(null, null, ['--debug', '--log-progress', $file], 0, $messages);
        $this->assertDirectoryExists("$dir/progress-log");

        $this->assertCommandProduces(null, null, ['--debug', $file], 0, $messages);
        $this->assertDirectoryDoesNotExist("$dir/progress-log");
    }

    public function testJsonSchema(): void
    {
        $file = dirname(__DIR__, 3) . '/resources/prettyphp-schema.json';
        $output = File::getContents($file);
        $args = ['_json_schema', 'JSON schema for pretty-php configuration files'];
        $this->assertCommandProduces($output, null, $args, 0, []);
    }

    /**
     * @param string[] $args
     * @param array<array{Level::*,string,2?:array<string,mixed>}>|null $messages
     */
    private function assertCommandProduces(
        ?string $output,
        ?string $input,
        array $args = [],
        int $exitStatus = 0,
        ?array $messages = null
    ): void {
        try {
            if ($input === null) {
                $this->expectOutputString((string) $output);
                $this->assertSame($exitStatus, $this->formatPhp(...$args));
                return;
            }

            $temp = $this->App->getTempPath();
            $src1 = tempnam($temp, 'src');
            $src2 = tempnam($temp, 'src');
            File::putContents($src1, $input);
            File::putContents($src2, (string) $output);

            $output .= $output;
            array_push($args, '--no-config', '-o', '-', '--');

            $this->expectOutputString($output);
            $this->assertSame($exitStatus, $this->formatPhp(...[...$args, $src1]));
            $this->assertSame($exitStatus, $this->formatPhp(...[...$args, $src2]));
        } catch (ExceptionInterface $ex) {
            if (!$exitStatus) {
                throw $ex;
            }
            $this->assertSame($exitStatus, $ex->getExitStatus());
            Console::exception($ex);
        } finally {
            if ($messages !== null) {
                $this->assertSameMessages(
                    $messages,
                    $this->ConsoleTarget->getMessages()
                );
            }
        }
    }

    /**
     * @param array<array{Level::*,string,2?:array<string,mixed>}> $expected
     * @param mixed[] $actual
     */
    private function assertSameMessages(array $expected, array $actual): void
    {
        foreach ($expected as $i => &$message) {
            $message[1] = Str::eolFromNative($message[1]);
            if (isset($actual[$i][1])) {
                $actual[$i][1] = substr($actual[$i][1], 0, strlen($message[1]));
            }
            if (!isset($message[2]) && isset($actual[$i][2])) {
                unset($actual[$i][2]);
            }
        }
        $this->assertEquals($expected, $actual);
    }

    private function formatPhp(string ...$args): int
    {
        $_SERVER['argv'] = [$_SERVER['SCRIPT_FILENAME'], ...$args];
        return $this->App->run()->getLastExitStatus();
    }

    /**
     * Get *.in files in the given directory
     *
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
            $file = (string) $file;
            $path = substr($file, $offset);
            yield $path => [$file];
        }
    }

    private static function normaliseUnifiedDiff(string $diff): string
    {
        if (\PHP_EOL === "\n") {
            return $diff;
        }

        return Pcre::replace('/^((?:[+-]{3}|@)\V*)\R/m', '$1' . "\n", $diff);
    }
}
