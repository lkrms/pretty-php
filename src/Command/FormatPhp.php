<?php declare(strict_types=1);

namespace Lkrms\Pretty\Command;

use FilesystemIterator as FS;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\BreakBeforeMultiLineList;
use Lkrms\Pretty\Php\Rule\BreakBetweenMultiLineItems;
use Lkrms\Pretty\Php\Rule\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterFn;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterNot;
use Lkrms\Pretty\Php\Rule\Extra\SuppressSpaceAroundStringOperator;
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
    private $SkipMap = [
        'simplify-strings'         => SimplifyStrings::class,
        'space-around-operators'   => SpaceOperators::class,
        'preserve-newlines'        => PreserveNewlines::class,
        'one-line-arguments'       => DeclareArgumentsOnOneLine::class,
        'blank-before-return'      => AddBlankLineBeforeReturn::class,
        'blank-before-declaration' => SpaceDeclarations::class,
        'break-between-items'      => BreakBetweenMultiLineItems::class,
        'align-chains'             => AlignChainedCalls::class,
        'align-lists'              => AlignLists::class,
        'indent-heredocs'          => ReindentHeredocs::class,
        'align-comments'           => AlignComments::class,
        'report-brackets'          => ReportUnnecessaryParentheses::class,
    ];

    /**
     * @var array<string,string>
     */
    private $RuleMap = [
        'align-assignments'  => AlignAssignments::class,
        'break-before-lists' => BreakBeforeMultiLineList::class,
        'no-concat-spaces'   => SuppressSpaceAroundStringOperator::class,
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
                        --skip align-chains,align-lists
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
                ->long('skip')
                ->short('i')
                ->valueName('RULE')
                ->description('Skip one or more rules')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipMap))
                ->multipleAllowed()
                ->envVariable('pretty_php_skip')
                ->keepEnv(),
            CliOption::build()
                ->long('rule')
                ->short('r')
                ->valueName('RULE')
                ->description('Add one or more non-standard rules')
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->RuleMap))
                ->multipleAllowed()
                ->envVariable('pretty_php_rule')
                ->keepEnv(),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
                    Do not add line breaks at the position of newlines in the input

                    Equivalent to:
                        --skip preserve-newlines
                    EOF),
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

                      -q = Don't report changed files  
                     -qq = Don't report code problems  
                    -qqq = Don't report progress
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
        return null;
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

        $skip  = $this->getOptionValue('skip');
        $rules = $this->getOptionValue('rule');
        if ($this->getOptionValue('ignore-newlines')) {
            $skip[] = 'preserve-newlines';
        }
        if ($this->getOptionValue('align-all')) {
            $rules[] = 'align-assignments';
        }
        if ($this->getOptionValue('laravel')) {
            $skip[]  = 'one-line-arguments';
            $skip[]  = 'break-between-items';
            $skip[]  = 'align-chains';
            $rules[] = 'no-concat-spaces';
            $rules[] = 'space-after-fn';
            $rules[] = 'space-after-not';
        }
        if ($this->Quiet > 1) {
            $skip[] = 'report-brackets';
        }
        $skip  = array_values(array_intersect_key($this->SkipMap, array_flip($skip)));
        $rules = array_values(array_intersect_key($this->RuleMap, array_flip($rules)));

        $in  = $this->expandPaths($this->getOptionValue('file'), $directoryCount);
        $out = $this->getOptionValue('output');
        if (!$in && stream_isatty(STDIN)) {
            throw new CliArgumentsInvalidException('FILE required when input is a TTY');
        } elseif (!$in || $in === ['-']) {
            $in  = ['php://stdin'];
            $out = ['-'];
        } elseif ($out && $out !== ['-'] && ($directoryCount || count($out) !== count($in))) {
            throw new CliArgumentsInvalidException('--output is required once per input file'
                . ($directoryCount ? ' and cannot be used with directories' : ''));
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
            $skip,
            $rules
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
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                $this->maybeDumpDebugOutput($input, null, $formatter->Tokens, (string) $ex);
                throw $ex;
            } finally {
                Sys::stopTimer($file, 'file');
            }
            $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens, null);

            $outFile = $out[$key] ?? '-';
            if ($outFile === '-') {
                print $output;
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
     * @param mixed $data
     */
    private function maybeDumpDebugOutput(string $input, ?string $output, ?array $tokens, $data): void
    {
        if (!is_null($this->DebugDirectory)) {
            foreach ([
                'input.php'   => $input,
                'output.php'  => $output,
                'tokens.json' => $tokens,
                'data.json'   => is_string($data) ? null : $data,
                'data.out'    => is_string($data) ? $data : null,
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
}
