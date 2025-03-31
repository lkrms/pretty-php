<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\App;

use Lkrms\PrettyPHP\App\PrettyPHPCommand;
use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\Cli\CliApplication;
use Salient\Console\Format\Formatter;
use Salient\Contract\Core\Exception\Exception;
use Salient\Core\Facade\Console;
use Salient\Core\Process;
use Salient\Testing\Console\MockTarget;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Json;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Sys;

/**
 * @backupGlobals enabled
 */
final class PrettyPHPCommandTest extends TestCase
{
    private const ERROR = Formatter::DEFAULT_LEVEL_PREFIX_MAP[Console::LEVEL_ERROR];
    private const WARNING = Formatter::DEFAULT_LEVEL_PREFIX_MAP[Console::LEVEL_WARNING];
    private const DEBUG = Formatter::DEFAULT_LEVEL_PREFIX_MAP[Console::LEVEL_DEBUG];
    private const SUMMARY = Formatter::DEFAULT_TYPE_PREFIX_MAP[Console::TYPE_SUMMARY];
    private const SUCCESS = Formatter::DEFAULT_TYPE_PREFIX_MAP[Console::TYPE_SUCCESS];

    private const SYNOPSIS = <<<'EOF'

pretty-php [-1OLTNSnMbvRq] [-I <regex>] [-X <regex>] [-P[<regex>]]
    [-i <rule>,...] [-r <rule>,...] [-h <level>] [-m <order>] [--psr12]
    [-c <file>] [--no-config] [-o <file>,...] [--diff[=<type>]] [--check]
    [--print-config] [--] [<path>...]

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
        parent::setUp();

        $this->ConsoleTarget = new MockTarget(null, true, true, false);
        Console::registerTarget($this->ConsoleTarget, Console::LEVELS_ALL_EXCEPT_DEBUG);

        $_SERVER['SCRIPT_FILENAME'] = 'pretty-php';

        $this->App = (new CliApplication(self::$BasePath))
                         ->oneCommand(PrettyPHPCommand::class);
    }

    protected function tearDown(): void
    {
        $this->App->unload();

        $targets = Console::getTargets();
        Console::unload();
        foreach ($targets as $target) {
            $target->close();
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        File::pruneDir(self::$BasePath, true);
    }

    /**
     * @dataProvider drupalProvider
     */
    public function testDrupal(string $file): void
    {
        $this->makePresetAssertions('drupal', $file);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function drupalProvider(): iterable
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
     * @return iterable<array{string}>
     */
    public static function laravelProvider(): iterable
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
     * @return iterable<array{string}>
     */
    public static function symfonyProvider(): iterable
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
     * @return iterable<array{string}>
     */
    public static function wordpressProvider(): iterable
    {
        yield from self::getInputFiles('preset/wordpress');
    }

    public function testWordpressWithTight(): void
    {
        $messages = [
            [Console::LEVEL_WARNING, self::WARNING . 'wordpress preset disabled tight declaration spacing'],
            [Console::LEVEL_INFO, self::SUCCESS . 'Formatted 1 file successfully'],
        ];
        foreach (self::getInputFiles('preset/wordpress') as [$file]) {
            $input = File::getContents($file);
            $expected = File::getContents(substr($file, 0, -3) . '.out');
            $this->assertCommandProduces(
                $expected,
                $input,
                ['--preset', 'wordpress', '--tight'],
                0,
                [...$messages, ...$messages],
            );
            break;
        }
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
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|null $messages
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
        $this->App->setInitialWorkingDirectory($dir);

        if ($inputFile !== null) {
            File::writeContents($file, $inputFile);
        }

        $this->assertCommandProduces($output, null, $args, $exitStatus, $messages);

        if ($outputFile !== null) {
            $this->assertSame($outputFile, File::getContents($file));
        }
    }

    /**
     * @return array<array{int,string|null,string|null,string|null,string[],5?:array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|null}>
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
                [[Console::LEVEL_INFO, self::SUCCESS . 'Formatted 1 file successfully']],
            ],
        ];
    }

    /**
     * @dataProvider directoriesProvider
     *
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|string|null $message
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
                : [[Console::LEVEL_INFO, $message]];
        } elseif ($exitStatus === 2) {
            $messages = [[Console::LEVEL_ERROR, self::ERROR . 'InvalidConfigurationException: ' . $message]];
        } else {
            $messages = [[Console::LEVEL_ERROR, (string) $message]];
        }
        $dir = self::$FixturesPath . $dir;
        if ($chdir) {
            File::chdir($dir);
            $this->App->setInitialWorkingDirectory($dir);
            $this->assertCommandProduces($output, null, $args, $exitStatus, $messages);
            return;
        }
        $this->assertCommandProduces($output, null, [...$args, '--', $dir], $exitStatus, $messages);
    }

    /**
     * @return array<array{int,array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|string|null,string,3?:bool,4?:string|null,...}>
     */
    public static function directoriesProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            'empty' => [
                0,
                self::SUCCESS . 'Formatted 0 files successfully',
                '/empty',
            ],
            'empty config' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/empty-config',
            ],
            'empty config in cwd' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
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
            'tight and disable declaration spacing' => [
                2,
                'tight and disable=declaration-spacing cannot both be given in ',
                '/tight-and-disable-declaration-spacing',
            ],
            'tight and disable declaration spacing in cwd' => [
                2,
                'tight and disable=declaration-spacing cannot both be given in ./.prettyphp',
                '/tight-and-disable-declaration-spacing',
                true,
            ],
            'no-sort-imports' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/no-sort-imports',
            ],
            'no-sort-imports in cwd' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
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
                self::SUMMARY . 'Found no unformatted files after checking 1 file in ',
                '/empty-config',
                false,
                null,
                '--check',
            ],
            'empty config + --check in cwd' => [
                0,
                self::SUMMARY . 'Found no unformatted files after checking 1 file in ',
                '/empty-config',
                true,
                null,
                '--check',
            ],
            'unformatted + --check' => [
                8,
                [[Console::LEVEL_INFO, self::SUMMARY . 'Found 1 unformatted file after checking 2 files in ']],
                '/unformatted',
                false,
                null,
                '--check',
            ],
            'unformatted + --check in cwd' => [
                8,
                [[Console::LEVEL_INFO, self::SUMMARY . 'Found 1 unformatted file after checking 2 files in ']],
                '/unformatted',
                true,
                null,
                '--check',
            ],
            'empty config + --diff' => [
                0,
                self::SUMMARY . 'Found no unformatted files after checking 1 file in ',
                '/empty-config',
                false,
                null,
                '--diff',
            ],
            'empty config + --diff in cwd' => [
                0,
                self::SUMMARY . 'Found no unformatted files after checking 1 file in ',
                '/empty-config',
                true,
                null,
                '--diff',
            ],
            'unformatted + --diff' => [
                8,
                [
                    [Console::LEVEL_INFO, self::SUMMARY . 'Found 1 unformatted file after checking 2 files in '],
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
                    [Console::LEVEL_INFO, self::SUMMARY . 'Found 1 unformatted file after checking 2 files in '],
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
                    [Console::LEVEL_ERROR, self::ERROR . "ParseError in $dir/invalid-syntax/invalid.php:4: "],
                    [Console::LEVEL_INFO, self::SUMMARY . 'Formatted 2 files with 1 error in '],
                ],
                '/invalid-syntax',
            ],
            'invalid syntax in cwd' => [
                4,
                [
                    [Console::LEVEL_ERROR, self::ERROR . 'ParseError in ./invalid.php:4: '],
                    [Console::LEVEL_INFO, self::SUMMARY . 'Formatted 2 files with 1 error in '],
                ],
                '/invalid-syntax',
                true,
            ],
            'operators mixed' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/operators-mixed',
            ],
            'operators first' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/operators-first',
            ],
            'operators last' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/operators-last',
            ],
            'sort imports by depth' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/sort-imports-by-depth',
            ],
            'sort imports by name' => [
                0,
                self::SUCCESS . 'Formatted 1 file successfully',
                '/sort-imports-by-name',
            ],
            'tight' => [
                8,
                [
                    [Console::LEVEL_INFO, self::SUMMARY . 'Found 1 unformatted file after checking 1 file in '],
                ],
                '/tight',
                false,
                self::normaliseUnifiedDiff(<<<EOF
--- a/$dir/tight/Foo.php
+++ b/$dir/tight/Foo.php
@@ -8,9 +8,7 @@
 class Foo
 {
     public int \$Bar;
-
     public string \$Baz;
-
     private array \$Qux;
 
     public function __construct()

EOF),
                '--diff',
            ],
            'invalid --enable value' => [
                0,
                [
                    [Console::LEVEL_WARNING, "Warning: invalid --enable value 'not-very-strict-expressions' (expected one or more of: "],
                    [Console::LEVEL_INFO, self::SUMMARY . 'Formatted 1 file with 0 errors and 1 warning in '],
                ],
                '/invalid-enable-value',
            ],
            'invalid --enable value in cwd' => [
                0,
                [
                    [Console::LEVEL_WARNING, "Warning: invalid --enable value 'not-very-strict-expressions' (expected one or more of: "],
                    [Console::LEVEL_INFO, self::SUMMARY . 'Formatted 1 file with 0 errors and 1 warning in '],
                ],
                '/invalid-enable-value',
                true,
            ],
        ];
    }

    /**
     * @dataProvider invalidOptionsProvider
     */
    public function testInvalidOptions(string $message, string ...$args): void
    {
        $dir = File::createTempDir($this->App->getTempPath());
        File::chdir($dir);
        $this->App->setInitialWorkingDirectory($dir);
        $this->assertCommandProduces(null, null, $args, 1, [
            [Console::LEVEL_ERROR, 'Error: ' . $message],
            [Console::LEVEL_INFO, self::SYNOPSIS],
        ]);
    }

    /**
     * @return array<array{string,...}>
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
                '-',
            ],
            'tab=1' => [
                "invalid --tab value '1' (expected one of: 2,4,8)",
                '--tab=1',
                '-',
            ],
            'space=6' => [
                "invalid --space value '6' (expected one of: 2,4,8)",
                '--space=6',
                '-',
            ],
            'operators first and last' => [
                '--operators-first and --operators-last cannot both be given',
                '--operators-first',
                '--operators-last',
                '-',
            ],
            'tight and disable declaration spacing' => [
                '--tight and --disable=declaration-spacing cannot both be given',
                '--tight',
                '--disable',
                'declaration-spacing',
                '-',
            ],
            'invalid sort-imports #1' => [
                '--sort-imports-by and --no-sort-imports/--disable=sort-imports cannot both be given',
                '--sort-imports-by',
                'name',
                '--no-sort-imports',
                '-',
            ],
            'invalid sort-imports #2' => [
                '--sort-imports-by and --no-sort-imports/--disable=sort-imports cannot both be given',
                '--sort-imports-by',
                'name',
                '--disable',
                'sort-imports',
                '-',
            ],
            'incompatible rules' => [
                'strict-expressions and semi-strict-expressions cannot be used together',
                '--enable',
                'strict-expressions,semi-strict-expressions',
                '-',
            ],
        ];
    }

    public function testUnseekableInput(): void
    {
        $file = self::$FixturesPath . '/empty-config/Foo.php';
        $input = File::getContents($file);
        $process = new Process([
            ...self::PHP_COMMAND,
            self::getPackagePath() . '/bin/pretty-php',
            '--debug',
            '-F',
            $file,
            '--',
            '-'
        ]);
        $pipe = $this->getUnseekableStream($input);
        try {
            $process->pipeInput($pipe);
            $this->assertSame(0, $process->run());
            $this->assertSame($input, $process->getOutput());
            $this->assertStringContainsString('Copying unseekable input to temporary stream', $process->getOutput(Process::STDERR));
        } finally {
            File::closePipe($pipe);
        }
    }

    /**
     * @return resource
     */
    private function getUnseekableStream(string $content)
    {
        $command = Sys::escapeCommand([
            ...self::PHP_COMMAND,
            '-r',
            sprintf('echo %s;', Get::code($content)),
        ]);

        return File::openPipe($command, 'r');
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
     * @return array<array{string,...}>
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
        File::writeContents($file, $config[1] . \PHP_EOL);
        $this->assertCommandProduces(
            $config[0] . \PHP_EOL,
            null,
            ['--config', $file, '--print-config'],
            0,
            [],
        );
    }

    /**
     * @return array<array{array{string,string}|string}>
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
     * @return array<array{string,...}>
     */
    public static function configProvider(): array
    {
        foreach (self::getConfigData() as $name => $config) {
            if (!is_string($config[0])) {
                $config[0] = Json::prettyPrint($config[0]);
            }
            $data[$name] = $config;
        }
        return $data;
    }

    /**
     * @return non-empty-array<string,array{array<string,mixed>|string,...}>
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
        $messages = [
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->doGetFormatter:'],
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->getConfigFile:'],
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->getConfigValues:'],
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->run:'],
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->doGetFormatter:'],
            [Console::LEVEL_DEBUG, self::DEBUG . '{' . PrettyPHPCommand::class . '->run:'],
            [Console::LEVEL_INFO, self::SUCCESS . 'Formatted 1 file successfully in '],
        ];
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
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}>|null $messages
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
            $src1 = "$temp/src1";
            $src2 = "$temp/src2";
            File::writeContents($src1, $input);
            File::writeContents($src2, (string) $output);

            $output .= $output;
            array_push($args, '--no-config', '-o', '-', '--');

            $this->expectOutputString($output);
            $this->assertSame($exitStatus, $this->formatPhp(...[...$args, $src1]));
            $this->assertSame($exitStatus, $this->formatPhp(...[...$args, $src2]));
        } catch (Exception $ex) {
            if (!$exitStatus) {
                throw $ex;
            }
            $this->assertSame($exitStatus, $ex->getExitStatus());
            Console::exception($ex);
        } finally {
            if ($messages !== null) {
                $this->assertSameMessages(
                    $messages,
                    $this->ConsoleTarget->getMessages(),
                    !$exitStatus || $exitStatus === 4 || $exitStatus === 8,
                );
            }
        }
    }

    /**
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}> $expected
     * @param array<array{Console::LEVEL_*,string,2?:array<string,mixed>}> $actual
     */
    private function assertSameMessages(
        array $expected,
        array $actual,
        bool $filterVersion
    ): void {
        if ($filterVersion) {
            $version = $this->App->getVersionString();
            foreach ($actual as $message) {
                if ($message !== [Console::LEVEL_INFO, $version]) {
                    $filtered[] = $message;
                }
            }
            $actual = $filtered ?? [];
        }
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
     * @return iterable<array{string}>
     */
    private static function getInputFiles(string $source): iterable
    {
        $dir = self::getFixturesPath(__CLASS__) . "/$source";
        $offset = strlen($dir) + 1;
        $files = File::find()
                     ->files()
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
        return Regex::replace('/^((?:-{3}|\+{3}|@)\V*+)\R/m', '$1' . "\n", $diff);
    }
}
