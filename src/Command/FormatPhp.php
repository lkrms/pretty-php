<?php declare(strict_types=1);

namespace Lkrms\Pretty\Command;

use FilesystemIterator as FS;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\CliUsageSectionName;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Filter\SortImports;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\BreakBeforeMultiLineList;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterFn;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterNot;
use Lkrms\Pretty\Php\Rule\Extra\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\Extra\SuppressSpaceAroundStringOperator;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\ReportUnnecessaryParentheses;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

class FormatPhp extends CliCommand
{
    /**
     * @var string
     */
    private $Include;

    /**
     * @var string
     */
    private $Exclude;

    /**
     * @var bool
     */
    private $Verbose;

    /**
     * @var int
     */
    private $Quiet;

    /**
     * @var string|null
     */
    private $DebugDirectory;

    /**
     * @var array<string,string>
     */
    private $SkipRuleMap = [
        'simplify-strings'         => SimplifyStrings::class,
        'space-around-operators'   => SpaceOperators::class,
        'preserve-newlines'        => PreserveNewlines::class,
        'blank-before-return'      => AddBlankLineBeforeReturn::class,
        'blank-before-declaration' => SpaceDeclarations::class,
        'align-chains'             => AlignChainedCalls::class,
        'align-lists'              => AlignLists::class,
        'indent-heredocs'          => ReindentHeredocs::class,
        'report-brackets'          => ReportUnnecessaryParentheses::class,
    ];

    /**
     * @var array<string,string>
     */
    private $AddRuleMap = [
        'align-assignments'  => AlignAssignments::class,
        'align-comments'     => AlignComments::class,
        'break-before-lists' => BreakBeforeMultiLineList::class,
        'no-concat-spaces'   => SuppressSpaceAroundStringOperator::class,
        'no-mixed-lists'     => NoMixedLists::class,
        'one-line-arguments' => DeclareArgumentsOnOneLine::class,
        'preserve-one-line'  => PreserveOneLineStatements::class,
        'space-after-fn'     => AddSpaceAfterFn::class,
        'space-after-not'    => AddSpaceAfterNot::class,
    ];

    public function getShortDescription(): string
    {
        return 'Format a PHP file';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('file')
                ->valueName('PATH')
                ->description(<<<EOF
                    Files and directories to format

                    If the only path is a dash ('-'), or no paths are given,
                    __{{command}}__ reads from the standard input and writes
                    formatted code to the standard output.

                    If PATH is a directory, __{{command}}__ searches for files
                    to format in the directory tree below PATH.
                    EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed(),
            CliOption::build()
                ->long('include')
                ->short('I')
                ->valueName('REGEX')
                ->description(<<<EOF
                    A regex that matches files to include when searching a PATH
                    EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\.php$/')
                ->bindTo($this->Include),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('REGEX')
                ->description(<<<EOF
                    A regex that matches files to exclude when searching a PATH
                    EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\/(\.git|\.hg|\.svn|_?build|dist|tests|var|vendor)\/$/')
                ->bindTo($this->Exclude),
            CliOption::build()
                ->long('tab')
                ->short('t')
                ->valueName('SIZE')
                ->description(<<<EOF
                    Indent using tabs

                    Implies:
                        --skip-rule align-chains,align-lists
                    EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4'),
            CliOption::build()
                ->long('space')
                ->short('s')
                ->valueName('SIZE')
                ->description('Indent using spaces')
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4'),
            CliOption::build()
                ->long('skip-rule')
                ->short('i')
                ->valueName('RULE')
                ->description('Skip one or more rules')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipRuleMap))
                ->multipleAllowed()
                ->envVariable('pretty_php_skip')
                ->keepEnv(),
            CliOption::build()
                ->long('rule')
                ->short('r')
                ->valueName('RULE')
                ->description('Add one or more non-standard rules')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->AddRuleMap))
                ->multipleAllowed()
                ->envVariable('pretty_php_rule')
                ->keepEnv(),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
                    Do not add line breaks at the position of newlines in the input

                    Equivalent to:
                        --skip-rule preserve-newlines
                    EOF),
            CliOption::build()
                ->long('no-simplify-strings')
                ->short('S')
                ->description(<<<EOF
                    Do not replace single- or double-quoted strings

                    Equivalent to:
                        --skip-rule simplify-strings
                    EOF),
            CliOption::build()
                ->long('no-sort-imports')
                ->short('M')
                ->description('Do not sort alias/import statements'),
            CliOption::build()
                ->long('align-all')
                ->short('a')
                ->description('Enable all alignment rules'),
            CliOption::build()
                ->long('laravel')
                ->short('l')
                ->description('Enable Laravel-friendly rules'),
            CliOption::build()
                ->long('output')
                ->short('o')
                ->valueName('FILE')
                ->description(<<<EOF
                    Write output to FILE instead of replacing the input file

                    If FILE is a dash ('-'), __{{command}}__ writes to the
                    standard output.

                    May be used once per input file.
                    EOF)
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed(),
            CliOption::build()
                ->long('debug')
                ->valueName('DIR')
                ->description('Create debug output in DIR')
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->app()->getTempPath() . '/debug'),
            CliOption::build()
                ->long('verbose')
                ->short('v')
                ->description('Report unchanged files')
                ->bindTo($this->Verbose),
            CliOption::build()
                ->long('quiet')
                ->short('q')
                ->description(<<<EOF
                    Only report errors

                    May be given multiple times to suppress more output.

                    _-q_ = Don't report changed files  
                    _-qq_ = Don't report code problems  
                    _-qqq_ = Don't report progress
                    EOF)
                ->multipleAllowed()
                ->bindTo($this->Quiet),
        ];
    }

    public function getLongDescription(): ?string
    {
        return null;
    }

    public function getUsageSections(): ?array
    {
        return [
            CliUsageSectionName::EXIT_STATUS => <<<EOF
                - _0_ if formatting succeeded  
                - _1_ if arguments were invalid  
                - _2_ if input was invalid (e.g. code has syntax errors)  
                - _3_ or above if an error occurred
                EOF,
        ];
    }

    protected function run(...$params)
    {
        $debug        = $this->getOptionValue('debug');
        $updateStderr = false;
        if (!is_null($debug)) {
            File::maybeCreateDirectory($debug);
            $debug = $this->DebugDirectory = realpath($debug) ?: null;
            if (!Env::debug()) {
                Env::debug(true);
                $updateStderr = true;
            }
            $this->app()->logConsoleMessages();
        }

        $tab   = Convert::toIntOrNull($this->getOptionValue('tab'));
        $space = Convert::toIntOrNull($this->getOptionValue('space'));
        if ($tab && $space) {
            throw new CliArgumentsInvalidException('--tab and --space cannot be used together');
        }

        $skipRules   = $this->getOptionValue('skip-rule');
        $addRules    = $this->getOptionValue('rule');
        $skipFilters = [];
        if ($this->getOptionValue('ignore-newlines')) {
            $skipRules[] = 'preserve-newlines';
        }
        if ($this->getOptionValue('no-simplify-strings')) {
            $skipRules[] = 'simplify-strings';
        }
        if ($this->getOptionValue('no-sort-imports')) {
            $skipFilters[] = SortImports::class;
        }
        if ($this->getOptionValue('align-all')) {
            $addRules[] = 'align-assignments';
            $addRules[] = 'align-comments';
        }
        if ($this->getOptionValue('laravel')) {
            $skipRules[] = 'one-line-arguments';
            $skipRules[] = 'align-chains';
            $addRules[]  = 'no-concat-spaces';
            $addRules[]  = 'space-after-fn';
            $addRules[]  = 'space-after-not';
        }
        if ($this->Quiet > 1) {
            $skipRules[] = 'report-brackets';
        }
        $skipRules = array_values(array_intersect_key($this->SkipRuleMap, array_flip($skipRules)));
        $addRules  = array_values(array_intersect_key($this->AddRuleMap, array_flip($addRules)));

        $in  = $this->expandPaths($this->getOptionValue('file'), $directoryCount);
        $out = $this->getOptionValue('output');
        if (!$in && stream_isatty(STDIN)) {
            throw new CliArgumentsInvalidException('FILE required when input is a TTY');
        } elseif (!$in || $in === ['-']) {
            $in  = ['php://stdin'];
            $out = ['-'];
        } elseif ($out && $out !== ['-'] && ($directoryCount || count($out) !== count($in))) {
            throw new CliArgumentsInvalidException(
                '--output is required once per input file'
                    . ($directoryCount ? ' and cannot be used with directories' : '')
            );
        } elseif (!$out) {
            $out = $in;
        }
        if ($out === ['-']) {
            Console::registerStderrTarget(true);
        } elseif ($updateStderr) {
            Console::registerStdioTargets(true);
        }

        $formatter = new Formatter(
            !$tab,
            $tab ?: $space ?: 4,
            $skipRules,
            $addRules,
            $skipFilters
        );
        $i      = 0;
        $count  = count($in);
        $errors = [];
        foreach ($in as $key => $file) {
            $this->Quiet > 2 || Console::logProgress(sprintf('Formatting %d of %d:', ++$i, $count), $file);
            $input = file_get_contents($file);
            Sys::startTimer($file, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    $this->Quiet,
                    $file === 'php://stdin'
                        ? null
                        : $file,
                );
            } catch (PrettyBadSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(2);
                $errors[] = $file;
                continue;
            } catch (PrettyException $ex) {
                Console::error('Unable to format:', $file);
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getLog(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                Console::error('Unable to format:', $file);
                $this->maybeDumpDebugOutput($input, null, $formatter->Tokens, $formatter->Log, (string) $ex);
                throw $ex;
            } finally {
                Sys::stopTimer($file, 'file');
            }
            $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens, $formatter->Log, null);

            $outFile = $out[$key] ?? '-';
            if ($outFile === '-') {
                Console::maybeClearLine();
                print $output;
                if (stream_isatty(STDOUT)) {
                    Console::tty('');
                }
                continue;
            }

            if (!Test::areSameFile($file, $outFile)) {
                $input = is_file($outFile) ? file_get_contents($outFile) : null;
            }

            if (!is_null($input) && $input === $output) {
                !$this->Verbose || Console::log('Already formatted:', $outFile);
                continue;
            }

            $this->Quiet || Console::log('Replacing', $outFile);
            file_put_contents($outFile, $output);
        }

        if ($errors) {
            Console::error(
                Convert::plural(count($errors), 'file', null, true) . ' with invalid syntax not formatted:',
                implode("\n", $errors),
                null,
                false
            );
        }

        $this->Quiet || Console::summary();
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function expandPaths(array $paths, ?int &$directoryCount): array
    {
        $directoryCount = 0;

        if ($paths === ['-']) {
            return $paths;
        }

        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                throw new CliArgumentsInvalidException('file not found: ' . $path);
            }
            $directoryCount++;

            $iterator = new RecursiveDirectoryIterator(
                $path,
                FS::KEY_AS_PATHNAME | FS::CURRENT_AS_FILEINFO | FS::SKIP_DOTS
            );
            $iterator = new RecursiveCallbackFilterIterator(
                $iterator,
                function (SplFileInfo $current, string $key): bool {
                    if (preg_match($this->Exclude, $key)) {
                        return false;
                    }
                    if ($current->isDir()) {
                        return !preg_match($this->Exclude, "$key/");
                    }

                    return (bool) preg_match($this->Include, $key);
                }
            );
            $iterator = new RecursiveIteratorIterator($iterator);
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $realpath = $file->getRealPath();
                if ($realpath === false) {
                    throw new RuntimeException('file not found: ' . $file);
                }
                $files[] = $realpath;
            }
        }

        if ($directoryCount) {
            ksort($files);
        }

        return $files;
    }

    /**
     * @param \Lkrms\Pretty\Php\Token[] $tokens
     * @param array<string,string>|null $log
     * @param mixed $data
     */
    private function maybeDumpDebugOutput(string $input, ?string $output, ?array $tokens, ?array $log, $data): void
    {
        if (is_null($this->DebugDirectory)) {
            return;
        }

        $logDir = "{$this->DebugDirectory}/render-log";
        File::maybeCreateDirectory($logDir);
        File::find($logDir, null, null, false)
            ->forEach(fn(SplFileInfo $file) => unlink($file->getPathname()));

        $i    = 0;
        $last = null;
        foreach ($log ?: [] as $after => $out) {
            if ($i++ && $out === $last) {
                continue;
            }
            $logFile = sprintf('render-log/%03d-%s.php', $i, $after);
            $last    = $logFiles[$logFile] = $out;
        }

        foreach ([
            'input.php'   => $input,
            'output.php'  => $output,
            'tokens.json' => $tokens,
            'data.json'   => is_string($data) ? null : $data,
            'data.out'    => is_string($data) ? $data : null,
            ...($logFiles ?? []),
        ] as $file => $contents) {
            $file = "{$this->DebugDirectory}/{$file}";
            File::maybeDelete($file);
            if (!is_null($contents)) {
                file_put_contents(
                    $file,
                    is_string($contents)
                        ? $contents
                        : json_encode($contents, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT)
                );
            }
        }
    }
}
