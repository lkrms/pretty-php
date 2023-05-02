<?php declare(strict_types=1);

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility;
use Lkrms\Cli\Catalog\CliUsageSectionName;
use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Console\ConsoleLevel;
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
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Utility\Test;
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
     * @var bool
     */
    private $AlignAll;

    /**
     * @var bool
     */
    private $Laravel;

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

    private const INTERNAL_OPTION_MAP = [
        'mirror-brackets' => 'MirrorBrackets',
        'hanging-heredoc-indents' => 'HangingHeredocIndents',
        'only-align-chained-statements' => 'OnlyAlignChainedStatements',
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

    public function getShortDescription(): string
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
A regex that matches files to include when searching a <PATH>

Exclusions ([__-X__, __--exclude__]) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\.php$/')
                ->bindTo($this->IncludeRegex),
            CliOption::build()
                ->long('include-if-php')
                ->short('P')
                ->valueName('REGEX')
                ->description(<<<EOF
Include files that contain PHP code when searching a <PATH>

Use this option to format files not matched by [__-I__, __--include__] if they
have a PHP open tag at the beginning of line 1, or on line 2 if a shebang ('#!')
is found on line 1.

The default regex matches files with no extension. Use __--include-if-php=/./__
to check the first line of all files.

Exclusions ([__-X__, __--exclude__]) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue('/(\/|^)[^.]+$/')
                ->bindTo($this->IncludeIfPhpRegex),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('REGEX')
                ->description(<<<EOF
A regex that matches files to exclude when searching a <PATH>

Exclusions are applied before inclusions ([__-I__, __--include__]).
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
                ->allowedValues(array_keys(self::ADD_RULE_MAP))
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
                ->visibility($noSynopsis)
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
                ->description(<<<EOF
Enable all alignment rules
EOF)
                ->visibility($noSynopsis)
                ->bindTo($this->AlignAll),
            CliOption::build()
                ->long('laravel')
                ->description(<<<EOF
Apply Laravel-friendly formatting
EOF)
                ->visibility($noSynopsis)
                ->bindTo($this->Laravel),
            CliOption::build()
                ->long('config')
                ->short('c')
                ->valueName('FILE')
                ->description(<<<EOF
Read formatting options from a JSON configuration file

Settings in <FILE> override formatting options given on the command line, and
any configuration files that would usually apply to the input are ignored.

If no files or directories to format are given, they are taken from the
configuration file. Input paths in <FILE> are ignored otherwise.
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
EOF)
                ->bindTo($this->IgnoreConfigFiles),
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
EOF)
                ->bindTo($this->PrintConfig),
            CliOption::build()
                ->long('stdin-filename')
                ->short('F')
                ->valueName('PATH')
                ->description(<<<EOF
The path of the file passed to the standard input

Allows discovery of configuration files. Useful for editor integrations.
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

If given twice, suppress warnings. If given three or more times, also suppress
TTY-only progress updates.

Errors are always reported.
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
            CliUsageSectionName::CONFIGURATION => <<<'EOF'
__{{command}}__ looks for a JSON configuration file in the same directory as
each input file, then in each of its parent directories, stopping as soon as it
finds one.

In order of precedence, files with the following names are recognised as
configuration files:

1. __.prettyphp__  
2. __prettyphp.json__  
3. __.prettyphp.dist__  
4. __prettyphp.json.dist__  
5. __prettyphp.dist.json__

If found, formatting options are taken from the configuration file, replacing
any provided on the command line or via environment variables.

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

The optional _src_ array specifies files and directories to format when no
<PATH> arguments are given on the command line, and either __{{command}}__ has
the same working directory as the configuration file, or the file is passed to
[__-c__, __--config__].
EOF,
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
        if (!is_null($this->DebugDirectory)) {
            File::maybeCreateDirectory($this->DebugDirectory);
            $this->DebugDirectory = realpath($this->DebugDirectory) ?: null;
            if (!Env::debug()) {
                Env::debug(true);
            }
            $this->app()->logConsoleMessages();
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot be given together');
        }
        if ($this->AlignAll) {
            $this->AddRules[] = 'align-assignments';
            $this->AddRules[] = 'align-chains';
            $this->AddRules[] = 'align-comments';
            $this->AddRules[] = 'align-fn';
            $this->AddRules[] = 'align-lists';
            $this->AddRules[] = 'align-ternary';
        }
        if ($this->Laravel) {
            $this->SkipRules = [];
            $this->SkipRules[] = 'magic-commas';

            $this->AddRules = [];
            $this->AddRules[] = 'space-after-fn';
            $this->AddRules[] = 'space-after-not';
            $this->AddRules[] = 'no-concat-spaces';
            $this->AddRules[] = 'align-chains';
            $this->AddRules[] = 'align-lists';
            $this->AddRules[] = 'indent-heredocs';

            // Laravel ternary operators (and chains, for that matter) don't
            // seem to follow any alignment rules, so this can be enabled or
            // disabled with little effect on the size of diffs:
            //
            //     $this->AddRules[] = 'align-ternary';

            $this->MirrorBrackets = false;
            $this->HangingHeredocIndents = false;
            $this->OnlyAlignChainedStatements = true;
        }
        $this->applyOptionValues([
            'disable' => $this->SkipRules,
            'enable' => $this->AddRules,
        ], false);

        if ($this->ConfigFile) {
            // If there are no paths on the command line, allow them to be taken
            // from the config file, otherwise only take formatting options
            $global = !$this->InputFiles &&
                !$this->StdinFilename &&
                !$this->PrintConfig;
            Console::debug($global
                ? 'Reading settings:'
                : 'Reading formatting options:', $this->ConfigFile);
            $this->applyFormattingOptionValues(
                $this->normaliseFormattingOptionValues(
                    json_decode(file_get_contents($this->ConfigFile), true), $global
                )
            );
            // Update any relative paths loaded from the config file
            if ($global &&
                    $this->InputFiles &&
                    !Test::areSameFile($dir = dirname($this->ConfigFile), getcwd())) {
                foreach ($this->InputFiles as &$file) {
                    if (Test::isAbsolutePath($file)) {
                        continue;
                    }
                    $file = $dir . DIRECTORY_SEPARATOR . $file;
                }
                unset($file);
            }
        } elseif (
            // If there are no paths on the command line (e.g. "pretty-php") or
            // one path that resolves to the script's current working directory
            // (e.g. "pretty-php ."), take files and directories to format from
            // the current directory's configuration file (if it exists)
            (!$this->InputFiles ||
                (count($this->InputFiles) === 1 &&
                    Test::areSameFile($this->InputFiles[0], getcwd()))) &&
                !$this->IgnoreConfigFiles &&
                !$this->StdinFilename &&
                !$this->PrintConfig &&
                $file = $this->maybeGetConfigFile(getcwd())
        ) {
            Console::debug('Reading settings:', $file);
            $this->applyFormattingOptionValues(
                $this->normaliseFormattingOptionValues(
                    json_decode(file_get_contents($file), true), true
                )
            );
        }
        Console::debug('Input paths:', '`' . (implode('` `', $this->InputFiles) ?: '<none>') . '`');

        // Save this configuration to restore as needed
        $this->CliFormattingOptionValues = $this->normaliseFormattingOptionValues(
            $this->getFormattingOptionValues(false, true), false, true
        );

        $in = $this->expandPaths($this->InputFiles, $dirCount, $dirs);
        $out = $this->OutputFiles;
        if (!$in && stream_isatty(STDIN) && !$this->StdinFilename && !$this->PrintConfig) {
            throw new CliInvalidArgumentsException('<PATH> required when input is a TTY');
        } elseif (!$in || $in === ['-']) {
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
                    if (!is_null($this->{$property})) {
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
                    $inputFile
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
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        foreach ([
            '.prettyphp',
            'prettyphp.json',
            '.prettyphp.dist',
            'prettyphp.json.dist',
            'prettyphp.dist.json',
        ] as $file) {
            $file = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
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
    private function normaliseFormattingOptionValues(array $values, bool $global = false, bool $internal = false): array
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
        $values = $this->normaliseOptionValues($values, true, [Convert::class, 'toKebabCase']);
        // If `$internal` is false, ignore `$values['@internal']` without
        // suppressing `$this->DefaultFormattingOptionValues['@internal']`
        $values = array_diff_key($values, $internal ? [] : ['@internal' => null]);

        return array_intersect_key(
            array_merge($this->DefaultFormattingOptionValues, $values),
            ($global ? $this->GlobalFormattingOptionNames : $this->FormattingOptionNames)
                + ['@internal' => null]
        );
    }

    /**
     * @param array<string,string|string[]|bool|int|null>|null $values
     * @return $this
     */
    private function applyFormattingOptionValues(?array $values = null)
    {
        if ($values !== null) {
            $this->applyOptionValues($values, false);
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
            'ignoreNewlines',
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
     * @param string[] $dirs
     * @return string[]
     */
    private function expandPaths(array $paths, ?int &$directoryCount, ?array &$dirs): array
    {
        $directoryCount = 0;
        $dirs = [];

        if ($paths === ['-']) {
            return $paths;
        }

        $addFile = function (SplFileInfo $file) use (&$files, &$_dirs): void {
            // Don't format the same file multiple times
            if (($files[$inode = $file->getInode()] ?? null) !== null) {
                return;
            }
            $files[$inode] = (string) $file;
            if ($this->IgnoreConfigFiles) {
                return;
            }
            $dir = (string) $file->getPathInfo();
            $_dirs[$dir] = $dir;
        };

        $files = [];
        $_dirs = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $addFile(new SplFileInfo($path));
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
                $addFile($file);
            }
        }

        $dirs = array_values($_dirs);

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
