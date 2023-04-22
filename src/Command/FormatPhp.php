<?php declare(strict_types=1);

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliUsageSectionName;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Facade\Test;
use Lkrms\Pretty\Php\Filter\SortImports;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AlignArrowFunctions;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\AlignTernaryOperators;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterFn;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterNot;
use Lkrms\Pretty\Php\Rule\Extra\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\Extra\SuppressSpaceAroundStringOperator;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use SplFileInfo;
use Throwable;

class FormatPhp extends CliCommand
{
    /**
     * @var string[]|null
     */
    private $InputFiles;

    /**
     * @var string
     */
    private $IncludeRegex;

    /**
     * @var string|null
     */
    private $IncludeIfPhpRegex;

    /**
     * @var string
     */
    private $ExcludeRegex;

    /**
     * @var int|null
     */
    private $Tabs;

    /**
     * @var int|null
     */
    private $Spaces;

    /**
     * @var string[]|null
     */
    private $SkipRules;

    /**
     * @var string[]|null
     */
    private $AddRules;

    /**
     * @var bool
     */
    private $OneTrueBraceStyle;

    /**
     * @var int[]|null
     */
    private $PreserveTrailingSpaces;

    /**
     * @var bool
     */
    private $IgnoreNewlines;

    /**
     * @var bool
     */
    private $NoSimplifyStrings;

    /**
     * @var bool
     */
    private $NoMagicCommas;

    /**
     * @var bool
     */
    private $NoSortImports;

    /**
     * @var bool
     */
    private $AlignAll;

    /**
     * @var bool
     */
    private $Laravel;

    /**
     * @var string[]|null
     */
    private $OutputFiles;

    /**
     * @var string|null
     */
    private $StdinFilename;

    /**
     * @var string|null
     */
    private $DebugDirectory;

    /**
     * @var bool
     */
    private $Verbose;

    /**
     * @var int
     */
    private $Quiet;

    // --

    /**
     * @var bool|null
     */
    private $MirrorBrackets;

    /**
     * @var bool|null
     */
    private $HangingHeredocIndents;

    /**
     * @var bool|null
     */
    private $OnlyAlignChainedStatements;

    /**
     * @var array<string,string>
     */
    private $SkipRuleMap = [
        'simplify-strings' => SimplifyStrings::class,
        'preserve-newlines' => PreserveNewlines::class,
        'magic-commas' => ApplyMagicComma::class,
        'declaration-spacing' => SpaceDeclarations::class,
        'indent-heredocs' => ReindentHeredocs::class,
    ];

    /**
     * @var array<string,string>
     */
    private $AddRuleMap = [
        'align-assignments' => AlignAssignments::class,
        'align-chains' => AlignChainedCalls::class,
        'align-comments' => AlignComments::class,
        'align-fn' => AlignArrowFunctions::class,
        'align-lists' => AlignLists::class,
        'align-ternary' => AlignTernaryOperators::class,
        'blank-before-return' => AddBlankLineBeforeReturn::class,
        'strict-lists' => NoMixedLists::class,
        'preserve-one-line' => PreserveOneLineStatements::class,

        // In the `Extra` namespace
        'space-after-fn' => AddSpaceAfterFn::class,
        'space-after-not' => AddSpaceAfterNot::class,
        'one-line-arguments' => DeclareArgumentsOnOneLine::class,
        'no-concat-spaces' => SuppressSpaceAroundStringOperator::class,
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

If the only path is a dash ('-'), or no paths are given, __{{command}}__ reads
from the standard input and writes to the standard output.

Directories are searched recursively for files to format.
EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
                ->bindTo($this->InputFiles),
            CliOption::build()
                ->long('include')
                ->short('I')
                ->valueName('REGEX')
                ->description(<<<EOF
A regex that matches files to include when searching a path

Exclusions (__--exclude__) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\.php$/')
                ->bindTo($this->IncludeRegex),
            CliOption::build()
                ->long('include-if-php')
                ->short('P')
                ->valueName('REGEX')
                ->description(<<<EOF
Include files that contain PHP code when searching a path

Use this option to format files not matched by __--include__ if they have a PHP
open tag at the beginning of line 1, or on line 2 if they have a shebang ('#!').

The default <REGEX> matches files with no extension. Use
__--include-if-php__=_._ to check the first line of all files.

Exclusions (__--exclude__) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue('/(\/|^)[^.]+$/')
                ->bindTo($this->IncludeIfPhpRegex),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('REGEX')
                ->description(<<<EOF
A regex that matches files to exclude when searching a path

Exclusions are applied before inclusions (__--include__).
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\/(\.git|\.hg|\.svn|_?build|dist|tests[-._0-9a-z]*|var|vendor)\/$/i')
                ->bindTo($this->ExcludeRegex),
            CliOption::build()
                ->long('tab')
                ->short('t')
                ->valueName('SIZE')
                ->description(<<<EOF
Indent using tabs

The __--enable__ values _align-chains_, _align-fn_, _align-lists_, and
_align-ternary_ have no effect when using tabs for indentation.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4')
                ->bindTo($this->Tabs),
            CliOption::build()
                ->long('space')
                ->short('s')
                ->valueName('SIZE')
                ->description(<<<EOF
Indent using spaces
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4')
                ->bindTo($this->Spaces),
            CliOption::build()
                ->long('disable')
                ->short('i')
                ->valueName('RULE')
                ->description(<<<EOF
Disable a standard formatting rule
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipRuleMap))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->envVariable('pretty_php_disable')
                ->keepEnv()
                ->bindTo($this->SkipRules),
            CliOption::build()
                ->long('enable')
                ->short('r')
                ->valueName('RULE')
                ->description(<<<EOF
Enable a non-standard formatting rule
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->AddRuleMap))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->envVariable('pretty_php_enable')
                ->keepEnv()
                ->bindTo($this->AddRules),
            CliOption::build()
                ->long('one-true-brace-style')
                ->short('1')
                ->description(<<<EOF
Format braces using the One True Brace Style
EOF)
                ->bindTo($this->OneTrueBraceStyle),
            CliOption::build()
                ->long('preserve-trailing-spaces')
                ->short('T')
                ->valueName('COUNT')
                ->description(<<<EOF
Preserve exactly <COUNT> trailing spaces in comments
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->multipleAllowed()
                ->defaultValue(['2'])
                ->bindTo($this->PreserveTrailingSpaces),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
Ignore the position of newlines in the input

Equivalent to:  __--disable__ _preserve-newlines_
EOF)
                ->bindTo($this->IgnoreNewlines),
            CliOption::build()
                ->long('no-simplify-strings')
                ->short('S')
                ->description(<<<EOF
Don't normalise single- and double-quoted strings

Equivalent to:  __--disable__ _simplify-strings_
EOF)
                ->bindTo($this->NoSimplifyStrings),
            CliOption::build()
                ->long('no-magic-commas')
                ->short('C')
                ->description(<<<EOF
Don't split lists with trailing commas into one item per line

Equivalent to:  __--disable__ _magic-commas_
EOF)
                ->bindTo($this->NoMagicCommas),
            CliOption::build()
                ->long('no-sort-imports')
                ->short('M')
                ->description(<<<EOF
Don't sort alias/import statements
EOF)
                ->bindTo($this->NoSortImports),
            CliOption::build()
                ->long('align-all')
                ->short('a')
                ->description(<<<EOF
Enable all alignment rules
EOF)
                ->bindTo($this->AlignAll),
            CliOption::build()
                ->long('laravel')
                ->short('l')
                ->description(<<<EOF
Apply Laravel-friendly formatting
EOF)
                ->bindTo($this->Laravel),
            CliOption::build()
                ->long('output')
                ->short('o')
                ->valueName('FILE')
                ->description(<<<EOF
Write output to a different file

If <FILE> is a dash ('-'), __{{command}}__ writes to the standard output.

May be given once per input file.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->OutputFiles),
            CliOption::build()
                ->long('stdin-filename')
                ->short('F')
                ->valueName('PATH')
                ->description(<<<EOF
The path of the file passed to the standard input

Allows discovery of configuration files. Useful for editor integrations.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->bindTo($this->StdinFilename),
            CliOption::build()
                ->long('debug')
                ->valueName('DIR')
                ->description(<<<EOF
Create debug output in <DIR>
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->hide(!Env::debug())
                ->defaultValue($this->app()->getTempPath() . '/debug')
                ->bindTo($this->DebugDirectory),
            CliOption::build()
                ->long('verbose')
                ->short('v')
                ->description(<<<EOF
Report unchanged files
EOF)
                ->bindTo($this->Verbose),
            CliOption::build()
                ->long('quiet')
                ->short('q')
                ->description(<<<EOF
Only report errors

May be given multiple times:

__-q__ suppresses file replacement reports  
__-qq__ also suppresses code problem reports  
__-qqq__ also suppresses TTY-only progress reports
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
_0_   Formatting succeeded / input already formatted  
_1_   Invalid arguments / input requires formatting  
_2_   Invalid input (code could not be parsed)  
_15_  Operational error
EOF,
        ];
    }

    protected function run(...$params)
    {
        $updateStderr = false;
        if (!is_null($this->DebugDirectory)) {
            File::maybeCreateDirectory($this->DebugDirectory);
            $this->DebugDirectory = realpath($this->DebugDirectory) ?: null;
            if (!Env::debug()) {
                Env::debug(true);
                $updateStderr = true;
            }
            $this->app()->logConsoleMessages();
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot be given together');
        }

        $skipRules = $this->SkipRules;
        $addRules = $this->AddRules;
        $skipFilters = [];
        if ($this->IgnoreNewlines) {
            $skipRules[] = 'preserve-newlines';
        }
        if ($this->NoSimplifyStrings) {
            $skipRules[] = 'simplify-strings';
        }
        if ($this->NoMagicCommas) {
            $skipRules[] = 'magic-commas';
        }
        if ($this->NoSortImports) {
            $skipFilters[] = SortImports::class;
        }
        if ($this->AlignAll) {
            $addRules[] = 'align-assignments';
            $addRules[] = 'align-chains';
            $addRules[] = 'align-comments';
            $addRules[] = 'align-fn';
            $addRules[] = 'align-lists';
            $addRules[] = 'align-ternary';
        }
        if ($this->Laravel) {
            $skipRules = [];
            $skipRules[] = 'magic-commas';

            $addRules = [];
            $addRules[] = 'space-after-fn';
            $addRules[] = 'space-after-not';
            $addRules[] = 'no-concat-spaces';
            $addRules[] = 'align-chains';
            $addRules[] = 'align-lists';
            $addRules[] = 'indent-heredocs';

            // Laravel ternary operators (and chains, for that matter) don't
            // seem to follow any alignment rules, so this can be enabled or
            // disabled with little effect on the size of diffs:
            //
            //     $addRules[] = 'align-ternary';

            $this->MirrorBrackets = false;
            $this->HangingHeredocIndents = false;
            $this->OnlyAlignChainedStatements = true;
        }
        if ($this->Quiet > 1) {
            $skipRules[] = 'report-brackets';
        }
        $skipRules = array_values(array_intersect_key($this->SkipRuleMap, array_flip($skipRules)));
        $addRules = array_values(array_intersect_key($this->AddRuleMap, array_flip($addRules)));

        $in = $this->expandPaths($this->InputFiles, $directoryCount);
        $out = $this->OutputFiles;
        if (!$in && stream_isatty(STDIN)) {
            throw new CliInvalidArgumentsException('<PATH> required when input is a TTY');
        } elseif (!$in || $in === ['-']) {
            $in = ['php://stdin'];
            $out = ['-'];
        } elseif ($out && $out !== ['-'] && ($directoryCount || count($out) !== count($in))) {
            throw new CliInvalidArgumentsException(
                '--output is required once per input file'
                    . ($directoryCount ? ' and cannot be given with directories' : '')
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
            !$this->Tabs,
            $this->Tabs ?: $this->Spaces ?: 4,
            $skipRules,
            $addRules,
            $skipFilters
        );
        $formatter->OneTrueBraceStyle = $this->OneTrueBraceStyle;
        if (!is_null($this->MirrorBrackets)) {
            $formatter->MirrorBrackets = $this->MirrorBrackets;
        }
        if (!is_null($this->PreserveTrailingSpaces)) {
            $formatter->PreserveTrailingSpaces = $this->PreserveTrailingSpaces;
        }
        if (!is_null($this->OnlyAlignChainedStatements)) {
            $formatter->OnlyAlignChainedStatements = $this->OnlyAlignChainedStatements;
        }
        if (!is_null($this->HangingHeredocIndents)) {
            $formatter->HangingHeredocIndents = $this->HangingHeredocIndents;
        }

        $i = 0;
        $count = count($in);
        $replaced = 0;
        $errors = [];
        foreach ($in as $key => $file) {
            $displayFile = ($file === 'php://stdin' ? $this->StdinFilename : null) ?: $file;
            $this->Quiet > 2 || Console::logProgress(sprintf('Formatting %d of %d:', ++$i, $count), $displayFile);
            $input = file_get_contents($file);
            Sys::startTimer($displayFile, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    $this->Quiet,
                    $displayFile
                );
            } catch (PrettyBadSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(2);
                $errors[] = $displayFile;
                continue;
            } catch (PrettyException $ex) {
                Console::error('Unable to format:', $displayFile);
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getLog(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                Console::error('Unable to format:', $displayFile);
                $this->maybeDumpDebugOutput($input, null, $formatter->Tokens, $formatter->Log, (string) $ex);
                throw $ex;
            } finally {
                Sys::stopTimer($displayFile, 'file');
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
            $replaced++;
        }

        if ($errors) {
            Console::error(
                Convert::plural(count($errors), 'file', null, true) . ' with invalid syntax not formatted:',
                implode("\n", $errors),
                null,
                false
            );
        }

        $this->Quiet || Console::summary(
            sprintf(
                $replaced ? 'Replaced %1$d of %2$d %3$s' : 'Formatted %2$d %3$s',
                $replaced,
                $count,
                Convert::plural($count, 'file')
            ),
            'successfully'
        );
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
                $files[fileinode($path)] = $path;
                continue;
            }
            if (!is_dir($path)) {
                throw new CliInvalidArgumentsException('file not found: ' . $path);
            }
            $directoryCount++;
            $iterator = File::find(
                $path,
                $this->ExcludeRegex,
                $this->IncludeRegex,
                null,
                $this->IncludeIfPhpRegex ? [
                    $this->IncludeIfPhpRegex =>
                        fn(SplFileInfo $file) => File::isPhp((string) $file)
                ] : null
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $files[$file->getInode()] = (string) $file;
            }
        }

        return array_values($files);
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
        File::find($logDir, null, null, null, null, false)
            ->forEach(fn(SplFileInfo $file) => unlink($file->getPathname()));

        $i = 0;
        $last = null;
        foreach ($log ?: [] as $after => $out) {
            if ($i++ && $out === $last) {
                continue;
            }
            $logFile = sprintf('render-log/%03d-%s.php', $i, $after);
            $last = $logFiles[$logFile] = $out;
        }

        foreach ([
            'input.php' => $input,
            'output.php' => $output,
            'tokens.json' => $tokens,
            'data.json' => is_string($data) ? null : $data,
            'data.out' => is_string($data) ? $data : null,
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
