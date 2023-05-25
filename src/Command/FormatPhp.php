<?php declare(strict_types=1);

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Console\Catalog\ConsoleLevel;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Contract\Filter;
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
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Utility\Test;
use RuntimeException;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SplFileInfo;
use Throwable;
use UnexpectedValueException;

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
     * @var string
     */
    private $ExcludeRegex;

    /**
     * @var string|null
     */
    private $IncludeIfPhpRegex;

    /**
     * @var int|null
     */
    private $Tabs;

    /**
     * @var int|null
     */
    private $Spaces;

    /**
     * @var string
     */
    private $Eol;

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
     * @var string|null
     */
    private $Preset;

    /**
     * @var string|null
     */
    private $ConfigFile;

    /**
     * @var bool
     */
    private $IgnoreConfigFiles;

    /**
     * @var string[]|null
     */
    private $OutputFiles;

    /**
     * @var string|null
     */
    private $Diff;

    /**
     * @var bool
     */
    private $Check;

    /**
     * @var bool
     */
    private $PrintConfig;

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
    private $ReportTimers;

    /**
     * @var bool
     */
    private $Fast;

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
     * @var array<class-string<Filter>>|null
     */
    private $SkipFilters;

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
     * [ Option name => null ]
     *
     * @var array<string,null>
     */
    private $FormattingOptionNames;

    /**
     * [ Option name => null ]
     *
     * @var array<string,null>
     */
    private $GlobalFormattingOptionNames;

    /**
     * [ Option name => default value ]
     *
     * @var array<string,string|string[]|bool|int|null>
     */
    private $DefaultFormattingOptionValues;

    /**
     * @var array<string,string|string[]|bool|int|null>|null
     */
    private $CliFormattingOptionValues;

    /**
     * @var array<string,array<string,string|string[]|bool|int|null>|null>
     */
    private $DirFormattingOptionValues = [];

    private const SKIP_RULE_MAP = [
        'simplify-strings' => SimplifyStrings::class,
        'preserve-newlines' => PreserveNewlines::class,
        'magic-commas' => ApplyMagicComma::class,
        'declaration-spacing' => SpaceDeclarations::class,
        'indent-heredocs' => ReindentHeredocs::class,
    ];

    private const ADD_RULE_MAP = [
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

    private const INCOMPATIBLE_RULES = [
        ['align-lists', 'strict-lists'],
    ];

    private const INTERNAL_OPTION_MAP = [
        'mirror-brackets' => 'MirrorBrackets',
        'hanging-heredoc-indents' => 'HangingHeredocIndents',
        'only-align-chained-statements' => 'OnlyAlignChainedStatements',
    ];

    private const PRESET_MAP = [
        'laravel' => [
            'disable' => [
                'magic-commas',
            ],
            'enable' => [
                'space-after-fn',
                'space-after-not',
                'no-concat-spaces',
                'align-lists',
                'blank-before-return',

                // Laravel chains and ternary operators don't seem to follow any
                // alignment rules, so these can be enabled or disabled with
                // little effect on the size of diffs:
                //
                'align-chains',
                //'align-ternary',
            ],
            '@internal' => [
                'mirror-brackets' => false,
                'hanging-heredoc-indents' => false,
                'only-align-chained-statements' => true,
            ],
        ],
    ];

    public function __construct(CliApplication $container)
    {
        parent::__construct($container);

        $this->FormattingOptionNames = $this->getFormattingOptionNames(false, true);
        $this->GlobalFormattingOptionNames = $this->getFormattingOptionNames(true, true);
        $this->DefaultFormattingOptionValues = array_intersect_key(
            $this->getDefaultOptionValues(), $this->GlobalFormattingOptionNames
        );
        foreach (self::INTERNAL_OPTION_MAP as $name => $property) {
            $this->DefaultFormattingOptionValues['@internal'][$name] = $this->{$property};
        }
    }

    public function description(): string
    {
        return 'Format a PHP file';
    }

    protected function getOptionList(): array
    {
        $noSynopsis = CliOptionVisibility::ALL & ~CliOptionVisibility::SYNOPSIS;
        $none = CliOptionVisibility::NONE;

        return [
            CliOption::build()
                ->long('src')
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
A regular expression for pathnames to include when searching a <PATH>

Exclusions (__-X__, __--exclude__) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\.php$/')
                ->bindTo($this->IncludeRegex),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('REGEX')
                ->description(<<<EOF
A regular expression for pathnames to exclude when searching a <PATH>

Exclusions are applied before inclusions (__-I__, __--include__).
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\/(\.git|\.hg|\.svn|_?build|dist|tests[-._0-9a-z]*|var|vendor)\/$/i')
                ->bindTo($this->ExcludeRegex),
            CliOption::build()
                ->long('include-if-php')
                ->short('P')
                ->valueName('REGEX')
                ->description(<<<EOF
Include files that contain PHP code when searching a <PATH>

Use this option to format files not matched by __-I__, __--include__ if they
have a pathname that matches <REGEX> and a PHP open tag ('<?php') at the start
of the first line that is not a shebang ('#!').

The default regular expression matches files with no extension. Use
__--include-if-php=/./__ to check the first line of all files.

Exclusions (__-X__, __--exclude__) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue('/(\/|^)[^.]+$/')
                ->bindTo($this->IncludeIfPhpRegex),
            CliOption::build()
                ->long('tab')
                ->short('t')
                ->valueName('SIZE')
                ->description(<<<EOF
Indent using tabs

The _align-chains_, _align-fn_, _align-lists_, and _align-ternary_ rules have no
effect when using tabs for indentation.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4')
                ->visibility($noSynopsis)
                ->bindTo($this->Tabs),
            CliOption::build()
                ->long('space')
                ->short('s')
                ->valueName('SIZE')
                ->description(<<<EOF
Indent using spaces

This is the default if neither __-t__, __--tab__ or __-s__, __--space__ are
given.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues(['2', '4', '8'])
                ->defaultValue('4')
                ->visibility($noSynopsis)
                ->bindTo($this->Spaces),
            CliOption::build()
                ->long('eol')
                ->short('l')
                ->valueName('SEQUENCE')
                ->description(<<<'EOF'
Set the output file's end-of-line sequence

In _platform_ mode, __{{command}}__ uses CRLF ("\r\n") line endings on Windows
and LF ("\n") on other platforms.

In _auto_ mode (the default), the input file's line endings are preserved, and
_platform_ mode is used as a fallback if there are no line breaks in the input.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['auto', 'platform', 'lf', 'crlf'])
                ->defaultValue('auto')
                ->visibility($noSynopsis)
                ->bindTo($this->Eol),
            CliOption::build()
                ->long('disable')
                ->short('i')
                ->valueName('RULE')
                ->description(<<<EOF
Disable a standard formatting rule
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::SKIP_RULE_MAP))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->bindTo($this->SkipRules),
            CliOption::build()
                ->long('enable')
                ->short('r')
                ->valueName('RULE')
                ->description(<<<EOF
Enable a non-standard formatting rule
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::ADD_RULE_MAP))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
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
                ->visibility($noSynopsis)
                ->bindTo($this->PreserveTrailingSpaces),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
Ignore the position of newlines in the input

This option cannot be overridden by configuration file settings (see
___CONFIGURATION___ below). Use __--disable__ _preserve-newlines_ to apply the
same formatting without overriding settings in any configuration files.
EOF)
                ->bindTo($this->IgnoreNewlines),
            CliOption::build()
                ->long('no-simplify-strings')
                ->short('S')
                ->description(<<<EOF
Don't normalise single- and double-quoted strings

Equivalent to __--disable__ _simplify-strings_
EOF)
                ->bindTo($this->NoSimplifyStrings),
            CliOption::build()
                ->long('no-magic-commas')
                ->short('C')
                ->description(<<<EOF
Don't split lists with trailing commas into one item per line

Equivalent to __--disable__ _magic-commas_
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
                ->long('preset')
                ->short('p')
                ->valueName('PRESET')
                ->description(<<<EOF
Apply a formatting preset

Formatting options other than __-N__, __--ignore-newlines__ are ignored when a
preset is applied.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::PRESET_MAP))
                ->visibility($noSynopsis)
                ->bindTo($this->Preset),
            CliOption::build()
                ->long('config')
                ->short('c')
                ->valueName('FILE')
                ->description(<<<EOF
Read formatting options from a JSON configuration file

Settings in <FILE> override formatting options given on the command line, and
any configuration files that would usually apply to the input are ignored.

See ___CONFIGURATION___ below.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->bindTo($this->ConfigFile),
            CliOption::build()
                ->long('no-config')
                ->description(<<<EOF
Ignore configuration files

Skip detection of configuration files that would otherwise take precedence over
formatting options given on the command line.

See ___CONFIGURATION___ below.
EOF)
                ->bindTo($this->IgnoreConfigFiles),
            CliOption::build()
                ->long('output')
                ->short('o')
                ->valueName('FILE')
                ->description(<<<EOF
Write output to a different file

If <FILE> is a dash ('-'), __{{command}}__ writes to the standard output.
Otherwise, __-o__, __--output__ must be given once per input file, or not at all.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->OutputFiles),
            CliOption::build()
                ->long('diff')
                ->valueName('TYPE')
                ->description(<<<EOF
Fail with a diff when the input is not already formatted
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(['unified', 'name-only'])
                ->defaultValue('unified')
                ->bindTo($this->Diff),
            CliOption::build()
                ->long('check')
                ->description(<<<EOF
Fail silently when the input is not already formatted
EOF)
                ->bindTo($this->Check),
            CliOption::build()
                ->long('print-config')
                ->description(<<<EOF
Print a configuration file instead of formatting the input

See ___CONFIGURATION___ below.
EOF)
                ->bindTo($this->PrintConfig),
            CliOption::build()
                ->long('stdin-filename')
                ->short('F')
                ->valueName('PATH')
                ->description(<<<EOF
The pathname of the file passed to the standard input

Allows discovery of configuration files and improves reporting. Useful for
editor integrations.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->visibility($noSynopsis)
                ->bindTo($this->StdinFilename),
            CliOption::build()
                ->long('debug')
                ->valueName('DIR')
                ->description(<<<EOF
Create debug output in <DIR>
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->app()->getTempPath() . '/debug')
                ->visibility(Env::debug() ? $noSynopsis : $none)
                ->bindTo($this->DebugDirectory),
            CliOption::build()
                ->long('timers')
                ->description(<<<EOF
Report timers and resource usage on exit
EOF)
                ->visibility(Env::debug() ? $noSynopsis : $none)
                ->bindTo($this->ReportTimers),
            CliOption::build()
                ->long('fast')
                ->description(<<<EOF
Skip equivalence checks
EOF)
                ->visibility($noSynopsis)
                ->bindTo($this->Fast),
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
Only report warnings and errors

If given twice, suppress warnings as well. If given three or more times, also
suppress TTY-only progress updates.

Errors are always reported.
EOF)
                ->multipleAllowed()
                ->bindTo($this->Quiet),
        ];
    }

    protected function getLongDescription(): ?string
    {
        return null;
    }

    protected function getHelpSections(): ?array
    {
        return [
            CliHelpSectionName::CONFIGURATION => <<<'EOF'
__{{command}}__ looks for a JSON configuration file named _.prettyphp_ or
_prettyphp.json_ in the same directory as each input file, then in each of its
parent directories. It stops looking when it finds a configuration file, a
_.git_ directory, a _.hg_ directory, or the root of the filesystem, whichever
comes first.

If a directory contains more than one configuration file, __{{command}}__
reports an error and exits without formatting anything.

For input files where an applicable configuration file is found, command-line
formatting options other than __-N__, __--ignore-newlines__ are replaced with
settings from the configuration file.

The __--print-config__ option can be used to generate a configuration file, for
example:

    $ __{{command}}__ -P -S -M --print-config src tests bootstrap.php
    {
        "src": [
            "src",
            "tests",
            "bootstrap.php"
        ],
        "includeIfPhp": null,
        "noSimplifyStrings": true,
        "noSortImports": true
    }

The optional _src_ array specifies files and directories to format. If
__{{command}}__ is started with no <PATH> arguments in a directory where _src_
is configured, or the directory is passed to __{{command}}__ for formatting,
paths in _src_ are formatted. It is ignored otherwise.
EOF,
            CliHelpSectionName::EXIT_STATUS => <<<EOF
_0_   Formatting succeeded / input already formatted  
_1_   Invalid arguments / input requires formatting  
_2_   Invalid input (code could not be parsed)  
_15_  Operational error
EOF,
        ];
    }

    protected function run(...$params)
    {
        if ($this->DebugDirectory !== null) {
            File::maybeCreateDirectory($this->DebugDirectory);
            $this->DebugDirectory = realpath($this->DebugDirectory) ?: null;
            if (!Env::debug()) {
                Env::debug(true);
            }
            $this->app()->logConsoleMessages();
        }

        if ($this->ReportTimers) {
            $this->App->registerShutdownReport(ConsoleLevel::NOTICE);
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot both be given');
        }

        if ($this->Preset) {
            $this->applyFormattingOptionValues(self::PRESET_MAP[$this->Preset]);
        }

        if ($this->ConfigFile) {
            $this->IgnoreConfigFiles = true;
            Console::debug('Reading formatting options:', $this->ConfigFile);
            $json = json_decode(file_get_contents($this->ConfigFile), true);
            $this->applyFormattingOptionValues(
                $this->normaliseFormattingOptionValues($json, false, false, false),
                true
            );
            $this->applyFormattingOptionValues(
                $this->normaliseFormattingOptionValues($json),
            );
        }

        $in = [];
        $dirs = [];
        $dirCount = 0;
        if (!$this->IgnoreConfigFiles &&
                ($this->InputFiles || !$this->StdinFilename) &&
                // Get files and directories to format from the current
                // directory's configuration file (if there are no paths on the
                // command line and a configuration file exists), or from the
                // configuration files of any directories on the command line
                // (if they exist)
                ($configFiles = array_filter(array_map(
                    fn(string $path) => is_dir($path) ? $this->maybeGetConfigFile($path) : null,
                    $this->InputFiles ?: ['.']
                )))) {
            // Take a backup of $this->InputFiles etc.
            $cliOptionValues = $this->normaliseFormattingOptionValues(
                $this->getFormattingOptionValues(true), true
            );
            $cliInputFiles = $this->InputFiles;
            foreach ($configFiles as $i => $configFile) {
                Console::debug('Reading settings:', $configFile);
                $json = file_get_contents($configFile);
                if (!$json) {
                    Console::debug('Ignoring empty file:', $configFile);
                    continue;
                }
                $json = json_decode($json, true);
                if (!is_array($json)) {
                    throw new CliInvalidArgumentsException(
                        sprintf('invalid configuration file: %s', $configFile)
                    );
                }
                if (Test::areSameFile($dir = dirname($configFile), getcwd())) {
                    $this->applyFormattingOptionValues(
                        $this->normaliseFormattingOptionValues($json, true, false, false),
                        true
                    );
                }
                $this->applyFormattingOptionValues(
                    $this->normaliseFormattingOptionValues($json, true)
                );
                $this->DirFormattingOptionValues[$dir] =
                    $this->normaliseFormattingOptionValues($json);
                if (!$this->InputFiles) {
                    continue;
                }
                $dirCount++;
                unset($cliInputFiles[$i]);
                // Update any relative paths loaded from the configuration file
                foreach ($this->InputFiles as &$file) {
                    if (Test::isAbsolutePath($file)) {
                        continue;
                    }
                    $file = $dir . '/' . $file;
                }
                unset($file);
                $this->expandPaths($this->InputFiles, $in, $dirs);
            }
            // Restore $this->InputFiles etc.
            ($dir ?? null) === '.' ||
                $this->applyFormattingOptionValues($cliOptionValues);
            // Remove directories that have already been expanded
            $this->InputFiles = $cliInputFiles;
        }

        // Save formatting options to restore as needed
        $this->CliFormattingOptionValues = $this->normaliseFormattingOptionValues(
            $this->getFormattingOptionValues(false, true), false, true
        );

        $this->expandPaths($this->InputFiles, $in, $dirs, $dirCount);
        $out = $this->OutputFiles;
        if (!$in && !$this->StdinFilename && !$this->PrintConfig && stream_isatty(STDIN)) {
            throw new CliInvalidArgumentsException('<PATH> required when input is a TTY');
        }
        if (!$in || $in === ['-']) {
            $in = ['php://stdin'];
            $out = ['-'];
            if ($this->StdinFilename && !$this->IgnoreConfigFiles) {
                $dirs[] = dirname($this->StdinFilename);
            }
        } elseif ($out && $out !== ['-'] && ($dirCount || count($out) !== count($in))) {
            throw new CliInvalidArgumentsException(
                '--output is required once per input file'
                    . ($dirCount ? ' and cannot be given with directories' : '')
            );
        } elseif (!$out) {
            $out = $in;
        }
        if ($out === ['-'] || $this->Diff || $this->Check || $this->PrintConfig) {
            Console::registerStderrTarget(true);
        }

        if ($this->PrintConfig) {
            printf("%s\n", json_encode($this->getFormattingOptionValues(true), JSON_PRETTY_PRINT));

            return;
        }

        // Resolve input file parent directories to their closest applicable
        // configuration files after sorting by longest name
        usort($dirs, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($dirs as $dir) {
            if (array_key_exists($dir, $this->DirFormattingOptionValues)) {
                continue;
            }
            $options = null;
            do {
                $last = $dir;
                $this->DirFormattingOptionValues[$dir] = &$options;
                if ($file = $this->maybeGetConfigFile($dir)) {
                    Console::debug('Configuration file found:', $file);
                    $options = $this->normaliseFormattingOptionValues(
                        json_decode(file_get_contents($file), true)
                    );
                    break;
                }
                if (is_dir($dir . '/.git') || is_dir($dir . '/.hg')) {
                    break;
                }
                if ($dir === '.') {
                    $dir = Sys::getCwd();
                }
                $dir = dirname($dir);
                if (array_key_exists($dir, $this->DirFormattingOptionValues)) {
                    $options = $this->DirFormattingOptionValues[$dir];
                    break;
                }
            } while ($dir !== $last);
            unset($options);
        }

        /** @var Formatter|null */
        $formatter = null;
        $lastOptions = null;
        $getFormatter =
            function (?string $file) use (&$formatter, &$lastOptions): Formatter {
                if (!$file || !($options = $this->DirFormattingOptionValues[dirname($file)] ?? null)) {
                    $options = $this->CliFormattingOptionValues;
                }
                if ($formatter && $options === $lastOptions) {
                    return $formatter;
                }
                Console::debug('New Formatter instance required for:', $file);
                $this->applyFormattingOptionValues($options);
                $f = new Formatter(
                    !$this->Tabs,
                    $this->Tabs ?: $this->Spaces ?: 4,
                    $this->SkipRules,
                    $this->AddRules,
                    $this->SkipFilters
                );
                $f->PreferredEol = $this->Eol === 'auto' || $this->Eol === 'platform'
                    ? PHP_EOL
                    : ($this->Eol === 'lf' ? "\n" : "\r\n");
                $f->PreserveEol = $this->Eol === 'auto';
                $f->OneTrueBraceStyle = $this->OneTrueBraceStyle;
                $f->PreserveTrailingSpaces = $this->PreserveTrailingSpaces ?: [];
                foreach (self::INTERNAL_OPTION_MAP as $property) {
                    if ($this->{$property} !== null) {
                        $f->{$property} = $this->{$property};
                    }
                }
                $lastOptions = $options;

                return $f;
            };

        $i = 0;
        $count = count($in);
        $replaced = 0;
        $errors = [];
        foreach ($in as $key => $file) {
            $inputFile = ($file === 'php://stdin' ? $this->StdinFilename : null) ?: $file;
            $this->Quiet > 2 || Console::logProgress(sprintf('Formatting %d of %d:', ++$i, $count), $inputFile);
            $input = file_get_contents($file);
            $formatter = $getFormatter($inputFile);
            Sys::startTimer($inputFile, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    $this->Quiet,
                    $inputFile,
                    $this->Fast
                );
            } catch (PrettyBadSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(2);
                $errors[] = $inputFile;
                continue;
            } catch (PrettyException $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getLog(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, null, $formatter->Tokens, $formatter->Log, (string) $ex);
                throw $ex;
            } finally {
                Sys::stopTimer($inputFile, 'file');
            }
            if ($i === $count) {
                $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens, $formatter->Log, null);
            }

            if ($this->Check) {
                if ($input === $output) {
                    continue;
                }
                $this->Quiet || Console::error('Input requires formatting');

                return 1;
            }

            if ($this->Diff) {
                if ($input === $output) {
                    !$this->Verbose || Console::log('Already formatted:', $inputFile);
                    continue;
                }
                Console::maybeClearLine();
                switch ($this->Diff) {
                    case 'name-only':
                        printf("%s\n", $inputFile);
                        break;
                    case 'unified':
                        print (new Differ(new StrictUnifiedDiffOutputBuilder([
                            'fromFile' => "a/$inputFile",
                            'toFile' => "b/$inputFile",
                        ])))->diff($input, $output);
                        $this->Quiet || Console::log('Would replace', $inputFile);
                }
                $replaced++;
                continue;
            }

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

            if ($input !== null && $input === $output) {
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

        if ($this->Check) {
            $this->Quiet || Console::log(sprintf(
                '%d %s would be left unchanged',
                $count,
                Convert::plural($count, 'file')
            ));

            return;
        }

        if ($this->Diff) {
            $this->Quiet || !$replaced || Console::out('', ConsoleLevel::INFO);
            $this->Quiet || Console::log(sprintf(
                $replaced
                    ? '%1$d of %2$d %3$s %4$s formatting'
                    : '%2$d %3$s would be left unchanged',
                $replaced,
                $count,
                Convert::plural($count, 'file'),
                Convert::plural($count, 'requires', 'require')
            ));

            return $replaced ? 1 : 0;
        }

        $this->Quiet || Console::summary(sprintf(
            $replaced ? 'Replaced %1$d of %2$d %3$s' : 'Formatted %2$d %3$s',
            $replaced,
            $count,
            Convert::plural($count, 'file')
        ), 'successfully');
    }

    private function maybeGetConfigFile(string $dir): ?string
    {
        $dir = dirname(($dir ?: '.') . '/.');
        foreach ([
            '.prettyphp',
            'prettyphp.json',
        ] as $file) {
            $file = $dir . '/' . $file;
            if (is_file($file)) {
                if ($found ?? null) {
                    throw new RuntimeException(sprintf('Too many configuration files: %s', $dir));
                }
                $found = $file;
            }
        }

        return $found ?? null;
    }

    /**
     * @return array<string,string|string[]|bool|int|null>
     */
    private function getFormattingOptionValues(bool $global, bool $internal = false): array
    {
        $options = $this->getOptionValues(true, [Convert::class, 'toCamelCase']);
        if ($this->Tabs) {
            $options['insertSpaces'] = false;
        }
        $tabSize = $this->Tabs ?: $this->Spaces ?: 4;
        if ($tabSize !== 4) {
            $options['tabSize'] = $tabSize;
        }
        $options = array_intersect_key(
            $options,
            $this->getFormattingOptionNames($global)
        );
        if ($internal) {
            foreach (self::INTERNAL_OPTION_MAP as $name => $property) {
                $options['@internal'][$name] = $this->{$property};
            }
        }

        return $options;
    }

    /**
     * @param array<string,string|string[]|bool|int|null> $values
     * @return array<string,string|string[]|bool|int|null>
     */
    private function normaliseFormattingOptionValues(array $values, bool $global = false, bool $internal = false, bool $expand = true): array
    {
        unset($values['tab'], $values['space']);
        if (array_key_exists('insertSpaces', $values)) {
            if (!$values['insertSpaces']) {
                $values['tab'] = $values['tabSize'] ?? 4;
            } else {
                $values['space'] = $values['tabSize'] ?? 4;
            }
        } elseif (array_key_exists('tabSize', $values)) {
            $values['space'] = $values['tabSize'];
        }
        unset($values['insertSpaces'], $values['tabSize']);
        $values = $this->normaliseOptionValues($values, $expand, [Convert::class, 'toKebabCase']);
        // If `$internal` is false, ignore `$values['@internal']` without
        // suppressing `$this->DefaultFormattingOptionValues['@internal']`
        $values = array_diff_key($values, $internal ? [] : ['@internal' => null]);

        return array_intersect_key(
            $expand ? array_merge($this->DefaultFormattingOptionValues, $values) : $values,
            ($global ? $this->GlobalFormattingOptionNames : $this->FormattingOptionNames)
                + ['@internal' => null]
        );
    }

    /**
     * @param array<string,string|string[]|bool|int|null>|null $values
     * @return $this
     */
    private function applyFormattingOptionValues(?array $values = null, bool $asArguments = false)
    {
        if ($values !== null) {
            $this->applyOptionValues($values, false, false, $asArguments);
            if ($internal = $values['@internal'] ?? null) {
                foreach ($internal as $name => $value) {
                    $property = self::INTERNAL_OPTION_MAP[$name] ?? null;
                    if (!$property) {
                        throw new UnexpectedValueException(sprintf('@internal option not recognised: %s', $name));
                    }
                    $this->{$property} = $value;
                }
            }
        }

        $this->SkipFilters = [];
        if ($this->IgnoreNewlines) {
            $this->SkipRules[] = 'preserve-newlines';
        }
        if ($this->NoSimplifyStrings) {
            $this->SkipRules[] = 'simplify-strings';
        }
        if ($this->NoMagicCommas) {
            $this->SkipRules[] = 'magic-commas';
        }
        if ($this->NoSortImports) {
            $this->SkipFilters[] = SortImports::class;
        }
        if ($this->Quiet > 1) {
            $this->SkipRules[] = 'report-brackets';
        }

        foreach (self::INCOMPATIBLE_RULES as $rules) {
            // If multiple rules from this group have been enabled, remove all
            // but the last
            if (count($rules = array_intersect($this->AddRules, $rules)) > 1) {
                array_pop($rules);
                $this->AddRules = array_diff_key($this->AddRules, $rules);
            }
        }

        $this->SkipRules = array_values(array_intersect_key(self::SKIP_RULE_MAP, array_flip($this->SkipRules)));
        $this->AddRules = array_values(array_intersect_key(self::ADD_RULE_MAP, array_flip($this->AddRules)));

        return $this;
    }

    /**
     * @return array<string,null>
     */
    private function getFormattingOptionNames(bool $global, bool $kebabCase = false): array
    {
        $names = $global
            ? ['src', 'include', 'includeIfPhp', 'exclude']
            : [];
        $names = [
            ...$names,
            'eol',
            'disable',
            'enable',
            'oneTrueBraceStyle',
            'preserveTrailingSpaces',
            //'ignoreNewlines',
            'noSimplifyStrings',
            'noMagicCommas',
            'noSortImports',
            'insertSpaces',
            'tabSize',
        ];

        return array_combine(
            $kebabCase
                ? array_map([Convert::class, 'toKebabCase'], $names)
                : $names,
            array_fill(0, count($names), null)
        );
    }

    /**
     * @param string[] $paths
     * @param string[] $files
     * @param string[] $dirs
     */
    private function expandPaths(array $paths, ?array &$files, ?array &$dirs, ?int &$dirCount = null): void
    {
        Console::debug('Expanding paths:', '`' . (implode('` `', $paths) ?: '<none>') . '`');

        if ($paths === ['-']) {
            $files[] = '-';

            return;
        }

        $addFile = function (SplFileInfo $file) use (&$files, &$dirs): void {
            // Don't format the same file multiple times
            if ($files[$inode = $file->getInode()] ?? null) {
                return;
            }
            $files[$inode] = (string) $file;
            if ($this->IgnoreConfigFiles) {
                return;
            }
            $dir = (string) $file->getPathInfo();
            $dirs[$dir] = $dir;
        };

        foreach ($paths as $path) {
            if (is_file($path)) {
                $addFile(new SplFileInfo($path));
                continue;
            }
            if (!is_dir($path)) {
                throw new CliInvalidArgumentsException(sprintf('file not found: %s', $path));
            }
            $dirCount++;
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
                $addFile($file);
            }
        }
    }

    /**
     * @param Token[]|null $tokens
     * @param array<string,string>|null $log
     * @param mixed $data
     */
    private function maybeDumpDebugOutput(string $input, ?string $output, ?array $tokens, ?array $log, $data): void
    {
        if ($this->DebugDirectory === null) {
            return;
        }

        $logDir = "{$this->DebugDirectory}/render-log";
        File::maybeCreateDirectory($logDir);
        File::find($logDir, null, null, null, null, false)
            ->forEach(fn(SplFileInfo $file) => unlink((string) $file));

        // Only dump output logged after something changed
        $i = 0;
        $last = null;
        foreach ($log ?: [] as $after => $out) {
            if ($i++ && $out === $last) {
                continue;
            }
            $logFile = sprintf('render-log/%03d-%s.php', $i, $after);
            $last = $logFiles[$logFile] = $out;
        }

        foreach (array_merge([
            'input.php' => $input,
            'output.php' => $output,
            'tokens.json' => $tokens,
            'data.json' => is_string($data) ? null : $data,
            'data.out' => is_string($data) ? $data : null,
        ], $logFiles ?? []) as $file => $contents) {
            $file = "{$this->DebugDirectory}/{$file}";
            File::maybeDelete($file);
            if ($contents !== null) {
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
