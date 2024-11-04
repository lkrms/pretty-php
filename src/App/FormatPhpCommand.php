<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\App;

use Lkrms\PrettyPHP\App\Exception\InvalidConfigurationException;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidFormatterException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\Preset\Drupal;
use Lkrms\PrettyPHP\Rule\Preset\Laravel;
use Lkrms\PrettyPHP\Rule\Preset\Symfony;
use Lkrms\PrettyPHP\Rule\Preset\WordPress;
use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Rule\PreserveNewlines;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Rule\SimplifyNumbers;
use Lkrms\PrettyPHP\Rule\SimplifyStrings;
use Lkrms\PrettyPHP\Rule\StrictExpressions;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Salient\Cli\Exception\CliInvalidArgumentsException;
use Salient\Cli\CliCommand;
use Salient\Cli\CliOption;
use Salient\Contract\Cli\CliHelpSectionName;
use Salient\Contract\Cli\CliOptionType;
use Salient\Contract\Cli\CliOptionValueType;
use Salient\Contract\Cli\CliOptionValueUnknownPolicy;
use Salient\Contract\Cli\CliOptionVisibility as Visibility;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Profile;
use Salient\Core\Indentation;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use JsonException;
use SplFileInfo;
use Throwable;

/**
 * Provides pretty-php's command-line interface
 */
final class FormatPhpCommand extends CliCommand
{
    private const DISABLE_MAP = [
        'sort-imports' => SortImports::class,
        'move-comments' => MoveComments::class,
        'simplify-strings' => SimplifyStrings::class,
        'simplify-numbers' => SimplifyNumbers::class,
        'preserve-newlines' => PreserveNewlines::class,
        'declaration-spacing' => DeclarationSpacing::class,
    ];

    private const ENABLE_MAP = [
        'align-comments' => AlignComments::class,
        'align-chains' => AlignChains::class,
        'align-fn' => AlignArrowFunctions::class,
        'align-ternary' => AlignTernaryOperators::class,
        'align-data' => AlignData::class,
        'align-lists' => AlignLists::class,
        'blank-before-return' => BlankBeforeReturn::class,
        'strict-expressions' => StrictExpressions::class,
        'strict-lists' => StrictLists::class,
        'preserve-one-line' => PreserveOneLineStatements::class,
    ];

    private const EOL_MAP = [
        'auto' => \PHP_EOL,
        'platform' => \PHP_EOL,
        'lf' => "\n",
        'crlf' => "\r\n",
    ];

    private const HEREDOC_INDENT_MAP = [
        'none' => HeredocIndent::NONE,
        'line' => HeredocIndent::LINE,
        'mixed' => HeredocIndent::MIXED,
        'hanging' => HeredocIndent::HANGING,
    ];

    private const IMPORT_SORT_ORDER_MAP = [
        'none' => ImportSortOrder::NONE,
        'name' => ImportSortOrder::NAME,
        'depth' => ImportSortOrder::DEPTH,
    ];

    /**
     * @var array<string,class-string<Preset>>
     */
    private const PRESET_MAP = [
        'drupal' => Drupal::class,
        'laravel' => Laravel::class,
        'symfony' => Symfony::class,
        'wordpress' => WordPress::class,
    ];

    private const CONFIG_FILE_NAME = [
        '.prettyphp',
        'prettyphp.json',
    ];

    private const SRC_OPTION_INDEX = [
        'src' => true,
        'include' => true,
        'exclude' => true,
        'includeIfPhp' => true,
    ];

    private const PROGRESS_LOG_DIR = 'progress-log';

    /** @var string[] */
    private array $InputFiles = [];
    private string $IncludeRegex = '';
    private string $ExcludeRegex = '';
    private ?string $IncludeIfPhpRegex = null;
    private ?int $Tabs = null;
    private ?int $Spaces = null;
    private string $Eol = '';
    /** @var string[] */
    private array $Disable = [];
    /** @var string[] */
    private array $Enable = [];
    private bool $OneTrueBraceStyle = false;
    private bool $OperatorsFirst = false;
    private bool $OperatorsLast = false;
    private bool $Tight = false;
    private bool $IgnoreNewlines = false;
    private bool $NoSimplifyStrings = false;
    private bool $NoSimplifyNumbers = false;
    private string $HeredocIndent = '';
    private string $SortImportsBy = '';
    private bool $NoSortImports = false;
    private bool $Psr12 = false;
    private ?string $Preset = null;
    private ?string $ConfigFile = null;
    private bool $IgnoreConfigFiles = false;
    /** @var string[] */
    private array $OutputFiles = [];
    private ?string $Diff = null;
    private bool $Check = false;
    private bool $PrintConfig = false;
    private ?string $StdinFilename = null;
    private ?string $DebugDirectory = null;
    private bool $LogProgress = false;
    private bool $ReportTimers = false;
    private bool $Fast = false;
    private bool $Verbose = false;

    /**
     * - 0 = print unformatted files, summary, warnings, TTY-only progress
     * - 1 = print summary, warnings, TTY-only progress
     * - 2 = print warnings, TTY-only progress
     * - 3 = print TTY-only progress
     * - 4 = only print errors
     */
    private int $Quiet = 0;

    // --

    /** @var array<string,Formatter|null> */
    private array $FormatterByDir;
    private Formatter $DefaultFormatter;
    /** @var array<string,array<string|int|bool|float>|string|int|bool|float|null> */
    private array $DefaultSchemaOptionValues;

    /**
     * Write verbose debug output to the console?
     */
    private bool $Debug;

    /**
     * @codeCoverageIgnore
     */
    public function getDescription(): string
    {
        return 'Format a PHP file';
    }

    /**
     * @inheritDoc
     */
    protected function getOptionList(): iterable
    {
        return [
            CliOption::build()
                ->name('src')
                ->valueName('path')
                ->description(<<<EOF
Files and directories to format.

If the only path is a dash ('-'), or no paths are given, `{{program}}` reads
from the standard input and writes to the standard output.

Directories are searched recursively for files to format.
EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->valueType(CliOptionValueType::PATH_OR_DASH)
                ->multipleAllowed()
                ->unique()
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->InputFiles),
            CliOption::build()
                ->long('include')
                ->short('I')
                ->valueName('regex')
                ->description(<<<EOF
A regular expression for pathnames to include when searching a directory.

Exclusions (`-X/--exclude`) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\.php$/')
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->IncludeRegex),
            CliOption::build()
                ->long('exclude')
                ->short('X')
                ->valueName('regex')
                ->description(<<<EOF
A regular expression for pathnames to exclude when searching a directory.

Exclusions are applied before inclusions (`-I/--include`).
EOF)
                ->optionType(CliOptionType::VALUE)
                ->defaultValue('/\/(\.git|\.hg|\.svn|_?build|dist|vendor)\/$/')
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->ExcludeRegex),
            CliOption::build()
                ->long('include-if-php')
                ->short('P')
                ->valueName('regex')
                ->description(<<<EOF
A regular expression for pathnames to check for PHP code when searching a
directory.

Use this option to format files not matched by `-I/--include` if they have a
pathname that matches <regex> and a PHP open tag ('\<?php') at the start of the
first line that is not a shebang ('#!').

The default regular expression matches files with no extension. Use
`--include-if-php=/./` to check the first line of all files.

Exclusions (`-X/--exclude`) are applied first.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue('/(\/|^)[^.]+$/')
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->IncludeIfPhpRegex),
            CliOption::build()
                ->long('tab')
                ->short('t')
                ->valueName('size')
                ->description(<<<EOF
Indent using tabs.

The *align-chains*, *align-fn*, *align-lists*, and *align-ternary* rules cannot
be enabled when using tabs for indentation.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues([2, 4, 8])
                ->defaultValue(4)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::SCHEMA)
                ->bindTo($this->Tabs),
            CliOption::build()
                ->long('space')
                ->short('s')
                ->valueName('size')
                ->description(<<<EOF
Indent using spaces.

This is the default if neither `-t/--tab` or `-s/--space` are given.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->valueType(CliOptionValueType::INTEGER)
                ->allowedValues([2, 4, 8])
                ->defaultValue(4)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::SCHEMA)
                ->bindTo($this->Spaces),
            CliOption::build()
                ->long('eol')
                ->short('l')
                ->valueName('sequence')
                ->description(<<<'EOF'
Set the output file's end-of-line sequence.

In *platform* mode, `{{program}}` uses CRLF ("\\r\\n") line endings on Windows
and LF ("\\n") on other platforms.

In *auto* mode, the input file's line endings are preserved, and *platform* mode
is used as a fallback if there are no line breaks in the input.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::EOL_MAP))
                ->defaultValue('auto')
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::SCHEMA)
                ->bindTo($this->Eol),
            CliOption::build()
                ->long('disable')
                ->short('i')
                ->valueName('rule')
                ->description(<<<EOF
Disable one of the default formatting rules.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::DISABLE_MAP))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->unique()
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->Disable),
            CliOption::build()
                ->long('enable')
                ->short('r')
                ->valueName('rule')
                ->description(<<<EOF
Enable an optional formatting rule.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::ENABLE_MAP))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->unique()
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->Enable),
            CliOption::build()
                ->long('one-true-brace-style')
                ->short('1')
                ->description(<<<EOF
Format braces using the One True Brace Style.
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->OneTrueBraceStyle),
            CliOption::build()
                ->long('operators-first')
                ->short('O')
                ->description(<<<EOF
Place newlines before operators when splitting code over multiple lines.
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->OperatorsFirst),
            CliOption::build()
                ->long('operators-last')
                ->short('L')
                ->description(<<<EOF
Place newlines after operators when splitting code over multiple lines.
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->OperatorsLast),
            CliOption::build()
                ->long('tight')
                ->short('T')
                ->description(<<<EOF
Remove blank lines between declarations of the same type where possible.

This option is not ignored when a configuration file is applied.
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->Tight),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
Ignore the position of newlines in the input.

Unlike `--disable=preserve-newlines`, this option is not ignored when a
configuration file is applied.
EOF)
                ->bindTo($this->IgnoreNewlines),
            CliOption::build()
                ->long('no-simplify-strings')
                ->short('S')
                ->description(<<<EOF
Don't normalise escape sequences in strings, and don't replace single- and
double-quoted strings with the most readable and economical syntax.

Equivalent to `--disable=simplify-strings`
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->NoSimplifyStrings),
            CliOption::build()
                ->long('no-simplify-numbers')
                ->short('n')
                ->description(<<<EOF
Don't normalise integers and floats.

Equivalent to `--disable=simplify-numbers`
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->NoSimplifyNumbers),
            CliOption::build()
                ->long('heredoc-indent')
                ->short('h')
                ->valueName('level')
                ->description(<<<EOF
Set the indentation level of heredocs and nowdocs.

If `--heredoc-indent=mixed` is given, line indentation is applied to heredocs
that start on their own line, otherwise hanging indentation is applied.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::HEREDOC_INDENT_MAP))
                ->defaultValue(array_flip(self::HEREDOC_INDENT_MAP)[HeredocIndent::MIXED])
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->HeredocIndent),
            CliOption::build()
                ->long('sort-imports-by')
                ->short('m')
                ->valueName('order')
                ->description(<<<EOF
Set the sort order for consecutive alias/import statements.

Use `--sort-imports-by=none` to group import statements by type without changing
their order.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::IMPORT_SORT_ORDER_MAP))
                ->defaultValue(array_flip(self::IMPORT_SORT_ORDER_MAP)[ImportSortOrder::DEPTH])
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->SortImportsBy),
            CliOption::build()
                ->long('no-sort-imports')
                ->short('M')
                ->description(<<<EOF
Don't sort or group consecutive alias/import statements.

Equivalent to `--disable=sort-imports`
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->NoSortImports),
            CliOption::build()
                ->long('psr12')
                ->description(<<<EOF
Enforce strict PSR-12 / PER Coding Style compliance.
EOF)
                ->visibility(Visibility::ALL | Visibility::SCHEMA)
                ->bindTo($this->Psr12),
            CliOption::build()
                ->long('preset')
                ->short('p')
                ->valueName('preset')
                ->description(<<<EOF
Apply a formatting preset.

Formatting options other than `-T/--tight`, `-N/--ignore-newlines` and `--psr12`
are ignored when a preset is applied.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::PRESET_MAP))
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::SCHEMA)
                ->bindTo($this->Preset),
            CliOption::build()
                ->long('config')
                ->short('c')
                ->valueName('file')
                ->description(<<<EOF
Read formatting options from a JSON configuration file.

Settings in <file> override command-line formatting options other than
`-T/--tight` and `-N/--ignore-newlines`, and any configuration files that would
usually apply to the input are ignored.

See `CONFIGURATION` below.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->valueType(CliOptionValueType::FILE)
                ->bindTo($this->ConfigFile),
            CliOption::build()
                ->long('no-config')
                ->description(<<<EOF
Ignore configuration files.

Use this option to ignore any configuration files that would usually apply to
the input.

See `CONFIGURATION` below.
EOF)
                ->bindTo($this->IgnoreConfigFiles),
            CliOption::build()
                ->long('output')
                ->short('o')
                ->valueName('file')
                ->description(<<<EOF
Write output to a different file.

If <file> is a dash ('-'), `{{program}}` writes to the standard output.
Otherwise, `-o/--output` must be given once per input file, or not at all.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->unique()
                ->bindTo($this->OutputFiles),
            CliOption::build()
                ->long('diff')
                ->valueName('type')
                ->description(<<<EOF
Fail with a diff when the input is not already formatted.
EOF)
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(['unified', 'name-only'])
                ->defaultValue('unified')
                ->bindTo($this->Diff),
            CliOption::build()
                ->long('check')
                ->description(<<<EOF
Fail silently when the input is not already formatted.
EOF)
                ->bindTo($this->Check),
            CliOption::build()
                ->long('print-config')
                ->description(<<<EOF
Print a configuration file instead of formatting the input.

See `CONFIGURATION` below.
EOF)
                ->bindTo($this->PrintConfig),
            CliOption::build()
                ->long('stdin-filename')
                ->short('F')
                ->valueName('path')
                ->description(<<<EOF
The pathname of the file passed to the standard input.

Allows discovery of configuration files and improves reporting. Useful for
editor integrations.
EOF)
                ->optionType(CliOptionType::VALUE)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
                ->bindTo($this->StdinFilename),
            CliOption::build()
                ->long('debug')
                ->valueName('directory')
                ->description(str_replace('{}', self::PROGRESS_LOG_DIR, <<<EOF
Create debug output in <directory>.

Combine with `--log-progress` to write partially formatted code to a series of
files in *\<directory>/{}* that represent changes applied by each enabled rule.
EOF))
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->App->getTempPath() . '/debug')
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::HIDE_DEFAULT)
                ->bindTo($this->DebugDirectory),
            CliOption::build()
                ->long('log-progress')
                ->description(<<<EOF
Write partially formatted code to files in the debug output directory.

This option has no effect if `--debug` is not given.
EOF)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
                ->bindTo($this->LogProgress),
            CliOption::build()
                ->long('timers')
                ->description(<<<EOF
Report timers and resource usage on exit.
EOF)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
                ->bindTo($this->ReportTimers),
            CliOption::build()
                ->long('fast')
                ->description(<<<EOF
Skip equivalence checks.
EOF)
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
                ->bindTo($this->Fast),
            CliOption::build()
                ->long('verbose')
                ->short('v')
                ->description(<<<EOF
Report unchanged files.
EOF)
                ->bindTo($this->Verbose),
            CliOption::build()
                ->long('quiet')
                ->short('q')
                ->description(<<<EOF
Do not report files that require formatting.

May be given multiple times for less verbose output:

- `-qq`: do not print version information or provide a summary of files
  formatted and replaced on exit.
- `-qqq`: suppress warnings.
- `-qqqq`: suppress TTY-only progress updates.

Errors are always reported.
EOF)
                ->multipleAllowed()
                ->bindTo($this->Quiet),
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getHelpSections(): array
    {
        return [
            CliHelpSectionName::CONFIGURATION => <<<'EOF'
`{{program}}` looks for a JSON configuration file named *.prettyphp* or
*prettyphp.json* in the same directory as each input file, then in each of its
parent directories. It stops looking when it finds a configuration file, a
*.git*, *.hg* or *.svn* directory, or the root of the filesystem, whichever
comes first.

If a configuration file is found, `{{program}}` formats the input using
formatting options read from the configuration file, and command-line formatting
options other than `-T/--tight` and `-N/--ignore-newlines` are ignored.

The `--print-config` option can be used to generate a configuration file, for
example:

```console
$ {{command}} --sort-imports-by=name --psr12 src tests --print-config
{
    "src": [
        "src",
        "tests"
    ],
    "sortImportsBy": "name",
    "psr12": true
}
```

The optional *src* array specifies files and directories to format when
`{{command}}` is started in the same directory or when the directory is passed
to `{{command}}` for formatting.

If a directory contains more than one configuration file, `{{program}}` reports
an error and exits without formatting anything.
EOF,
            CliHelpSectionName::EXIT_STATUS => <<<EOF
- *0* when formatting succeeds or the input is already formatted
- *1* when invalid arguments are given
- *2* when invalid configuration files are found
- *4* when one or more input files cannot be parsed
- *8* when formatting is required and `--diff` or `--check` are given
EOF,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function filterJsonSchema(array $schema): array
    {
        $schema['properties'] =
            Arr::spliceByKey($schema['properties'], 'tab', 2, [
                'insertSpaces' => [
                    'description' => 'Indent using spaces.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'tabSize' => [
                    'description' => 'The size of a tab in spaces.',
                    'enum' => [2, 4, 8],
                    'default' => 4,
                ],
            ]);

        return $schema;
    }

    /**
     * @inheritDoc
     */
    protected function filterGetSchemaValues(array $values): array
    {
        // If a preset is given, remove every formatting option other than
        // `--preset`, `--psr12` and `--tight`
        if (isset($values['preset'])) {
            return array_intersect_key($values, self::SRC_OPTION_INDEX + [
                'preset' => true,
                'psr12' => true,
                'tight' => true,
            ]);
        }

        /** @var int|bool|null */
        $tab = $values['tab'] ?? null;
        /** @var int|bool|null */
        $space = $values['space'] ?? null;
        if ($space || $tab) {
            $values['insertSpaces'] = $space ? true : false;
            $tabSize = $space ?: $tab ?: 4;
            if ($tabSize === true) {
                $tabSize = 4;
            }
            $values['tabSize'] = $tabSize;
        }
        unset($values['tab'], $values['space']);
        return $values;
    }

    /**
     * @inheritDoc
     */
    protected function filterNormaliseSchemaValues(array $values): array
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
        return $values;
    }

    /**
     * @inheritDoc
     */
    protected function run(...$params)
    {
        $this->FormatterByDir = [];
        unset($this->DefaultFormatter);

        if ($this->DebugDirectory !== null) {
            File::createDir($this->DebugDirectory);
            $this->DebugDirectory = File::realpath($this->DebugDirectory);
            Env::setDebug(true);
            $this->App->logOutput();
            $this->Debug = true;
        } else {
            $this->Debug = Env::getDebug();
        }

        Console::registerStderrTarget();

        if ($this->ReportTimers) {
            $this->App->registerShutdownReport(Level::NOTICE);
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot both be given');
        }

        $this->validateOptions();

        if ($this->ConfigFile !== null) {
            $this->IgnoreConfigFiles = true;
            // 1. Combine non-formatting options given on the command line with
            //    formatting options from the configuration file to ensure
            //    non-formatting options appear in `--print-config` output
            $config = array_intersect_key(
                $this->getOptionValues(true, true, true),
                self::SRC_OPTION_INDEX,
            ) + $this->getFormattingConfigValues($this->ConfigFile, $this->PrintConfig);
            $defaults = $this->getDefaultFormattingOptionValues();
            // 2. Set the value of every option
            $this->applyOptionValues($config + $defaults, true, true, true);
            // 3. Set the value of configured options as if they had been given
            //    on the command line
            $this->applyOptionValues($config, true, true, true, true, true);
            $this->validateOptions(InvalidConfigurationException::class, $this->ConfigFile);
            if ($this->Debug) {
                Console::debug(sprintf(
                    'Applied formatting options from %s:',
                    $this->ConfigFile,
                ), Json::prettyPrint($config));
            }
        }

        $in = [];
        $dirs = [];
        $dirCount = 0;

        if ($this->InputFiles === ['-']) {
            $in[] = '-';
        } elseif (in_array('-', $this->InputFiles, true)) {
            throw new CliInvalidArgumentsException("<path> cannot be '-' when multiple paths are given");
        } elseif (
            !$this->IgnoreConfigFiles
            && !$this->PrintConfig
            && ($this->InputFiles !== [] || $this->StdinFilename === null)
        ) {
            // Get files and directories to format from the current directory's
            // configuration file (if there are no paths on the command line and
            // a configuration file exists), or from the configuration files of
            // any directories on the command line (if they exist)
            $paths = $this->InputFiles === []
                ? ['.']
                : $this->InputFiles;
            foreach ($paths as $path) {
                if (!is_dir($path)) {
                    $this->addFile($path, $in, $dirs);
                    continue;
                }

                $configFile = $this->getConfigFile($path);
                $configValues = $configFile === null
                    ? null
                    : $this->getConfigValues($configFile);

                if ($configValues === null) {
                    // Bail out if there are no paths on the command line, and
                    // no configuration file in the current directory
                    if ($this->InputFiles === []) {
                        break;
                    }
                    $dirCount++;
                    $this->addDir($path, $in, $dirs);
                    continue;
                }

                $dirCount++;

                $dir = dirname($configFile);
                $restoreDir = false;
                if (!File::same($dir, File::getCwd())) {
                    Console::debug('Changing to directory:', $dir);
                    File::chdir($dir);
                    $restoreDir = true;
                }
                try {
                    $config = $this->getConfig($configValues);
                } finally {
                    if ($restoreDir) {
                        Console::debug('Returning to:', $this->App->getWorkingDirectory());
                        $this->App->restoreWorkingDirectory();
                    }
                }
                $config->validateOptions(InvalidConfigurationException::class, $configFile);
                if ($this->Debug) {
                    Console::debug(sprintf(
                        'Applied options from %s to instance #%d:',
                        $configFile,
                        spl_object_id($config),
                    ), Json::prettyPrint($configValues));
                }
                $this->FormatterByDir[$dir] = $config->getFormatter(InvalidConfigurationException::class);

                if (!$config->InputFiles) {
                    $this->addDir($path, $in, $dirs);
                    continue;
                }

                foreach ($config->InputFiles as $path) {
                    if (File::isAbsolute($path)) {
                        throw new InvalidConfigurationException(sprintf(
                            'Path cannot be absolute in %s: %s',
                            $configFile,
                            $path,
                        ));
                    }
                    $path = File::resolvePath($path);
                    if ($path !== '') {
                        $path = $dir . '/' . $path;
                        if (is_file($path)) {
                            $config->addFile($path, $in, $dirs);
                            continue;
                        }
                    } else {
                        $path = $dir;
                    }
                    if ($path === $dir || is_dir($path)) {
                        $config->addDir($path, $in, $dirs);
                        continue;
                    }
                    throw new InvalidConfigurationException(sprintf(
                        'File not found in %s: %s',
                        $configFile,
                        $path,
                    ));
                }
            }
        } else {
            foreach ($this->InputFiles as $path) {
                if (!is_dir($path)) {
                    $this->addFile($path, $in, $dirs);
                    continue;
                }
                $dirCount++;
                if (!$this->PrintConfig) {
                    $this->addDir($path, $in, $dirs);
                }
            }
        }

        if (
            !$in
            && !$dirCount
            && $this->InputFiles === []
            && !$this->PrintConfig
            && $this->StdinFilename === null
            && stream_isatty(\STDIN)
        ) {
            throw new CliInvalidArgumentsException('<path> required when input is a TTY');
        }

        $in = array_values($in);
        $out = $this->OutputFiles;

        $errors = [];
        if ((!$in && !$dirCount && $this->InputFiles === []) || $in === ['-']) {
            $in = ['php://stdin'];
            if ($this->StdinFilename !== null && !$this->IgnoreConfigFiles) {
                // `$this->StdinFilename` may not exist or even be a valid path,
                // but `$this->getConfigFile()` doesn't expect it to be
                $dir = dirname($this->StdinFilename);
                $dirs[$dir] = $dir;
            }
            if (!$out) {
                $out = ['-'];
            } elseif (count($out) > 1) {
                $errors[] = '--output cannot be given multiple times when reading from the standard input';
                $out = ['-'];
            }
        } elseif ($out && $out !== ['-']) {
            if (in_array('-', $out, true)) {
                $errors[] = "--output cannot be '-' when given multiple times";
            }
            if ($dirCount) {
                $errors[] = "--output must be '-' when formatting directories";
            }
            if (!$errors && count($out) !== count($in)) {
                $errors[] = "--output must be '-' or given once per input file";
            }
        } elseif (!$out) {
            $out = $in;
        }

        if ($out && $out !== ['-']) {
            foreach ($out as $file) {
                if (!file_exists($file)) {
                    $dir = dirname($file);
                    if (!is_dir($dir) || !is_writable($dir)) {
                        $errors[] = sprintf('not a writable directory: %s', $dir);
                    }
                    continue;
                }
                if (!is_file($file) || !is_writable($file)) {
                    $errors[] = sprintf('not a writable file: %s', $file);
                }
            }
        }

        if ($errors) {
            $errors = Arr::unique($errors);
            throw new CliInvalidArgumentsException(...$errors);
        }

        if ($this->Quiet < 2) {
            $this->App->reportVersion();
        }

        if ($this->PrintConfig) {
            $values = $this->getOptionValues(true, true, true);
            echo Json::prettyPrint(
                $values,
                $values ? 0 : \JSON_FORCE_OBJECT
            ) . \PHP_EOL;

            return;
        }

        // Resolve input directories to the closest applicable configuration
        // file after sorting by longest name
        usort(
            $dirs,
            fn(string $a, string $b): int =>
                strlen($b) <=> strlen($a)
        );

        foreach ($dirs as $dir) {
            if (array_key_exists($dir, $this->FormatterByDir)) {
                continue;
            }
            $formatter = null;
            do {
                $last = $dir;
                $this->FormatterByDir[$dir] = &$formatter;
                $configFile = $this->getConfigFile($dir);
                if ($configFile !== null) {
                    $configValues = $this->getFormattingConfigValues($configFile);
                    $config = $this->getConfig($configValues);
                    $config->validateOptions(InvalidConfigurationException::class, $configFile);
                    if ($this->Debug) {
                        Console::debug(sprintf(
                            'Applied formatting options from %s to instance #%d:',
                            $configFile,
                            spl_object_id($config),
                        ), Json::prettyPrint($configValues));
                    }
                    $formatter = $config->getFormatter(InvalidConfigurationException::class);
                    break;
                }
                if (
                    is_dir($dir . '/.git')
                    || is_dir($dir . '/.hg')
                    || is_dir($dir . '/.svn')
                ) {
                    Console::debug('No configuration file found in project:', $dir);
                    break;
                }
                if ($dir === '.') {
                    $dir = File::getCwd();
                }
                $dir = dirname($dir);
                if (array_key_exists($dir, $this->FormatterByDir)) {
                    $formatter = $this->FormatterByDir[$dir];
                    break;
                }
            } while ($dir !== $last);
            unset($formatter);
        }

        $i = 0;
        $count = count($in);
        $replaced = 0;
        $unchanged = 0;
        /** @var array<Throwable|string> */
        $errors = [];

        foreach ($in as $key => $file) {
            $i++;

            $inputFile = Str::coalesce(
                $file === 'php://stdin' ? $this->StdinFilename : null,
                $file,
            );

            if ($this->Debug) {
                Console::debug(sprintf(
                    'Formatting %d of %d:',
                    $i,
                    $count,
                ), $inputFile);
            }

            if (
                $this->Quiet < 4
                && ($file !== 'php://stdin' || (
                    $this->StdinFilename !== null && !stream_isatty(\STDIN)
                ))
            ) {
                Console::logProgress(sprintf(
                    'Formatting %d of %d:',
                    $i,
                    $count,
                ), $inputFile);
            }

            $dir = dirname($inputFile);
            $formatter = $this->FormatterByDir[$dir] ??=
                $this->DefaultFormatter ??=
                $this->getFormatter();

            $inputStream = File::open($file, 'rb');

            if (!File::isSeekableStream($inputStream)) {
                Console::debug('Copying unseekable input to temporary stream');
                $inputStream = File::getSeekableStream($inputStream, $file);
            }

            $defaultIndent = $formatter->getIndentation();
            $indent = Indentation::from($inputStream, $defaultIndent, false, $file);
            if ($indent !== $defaultIndent) {
                Console::debug(
                    'Input indentation appears to be:',
                    sprintf(
                        $indent->InsertSpaces ? '%d spaces' : 'tabs (size: %d)',
                        $indent->TabSize,
                    )
                );
            }

            File::rewind($inputStream, $file);
            $eol = File::getEol($inputStream, $file);
            $input = File::getContents($inputStream, 0, $file);
            File::close($inputStream, $file);

            Profile::startTimer($inputFile, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    $eol,
                    $indent,
                    $file !== 'php://stdin' || $this->StdinFilename !== null
                        ? $inputFile
                        : null,
                    $this->Fast,
                );
            } catch (InvalidSyntaxException $ex) {
                $errors[] = $ex;
                $this->setExitStatus(4);
                continue;
            } catch (FormatterException $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getLog(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, null, $formatter->getTokens(), $formatter->Log, (string) $ex);
                throw $ex;
            } finally {
                Profile::stopTimer($inputFile, 'file');
            }

            if ($formatter->Problems) {
                foreach ($formatter->Problems as $problem) {
                    $errors[] = (string) $problem;
                }
            }

            if ($i === $count) {
                $this->maybeDumpDebugOutput($input, $output, $formatter->getTokens(), $formatter->Log, null);
            }

            if ($this->Diff !== null || $this->Check) {
                if ($input === $output) {
                    continue;
                }
                $replaced++;
                if ($this->Diff === null) {
                    continue;
                }
                if ($this->Diff === 'unified') {
                    $diff = (new Differ(new StrictUnifiedDiffOutputBuilder([
                        'fromFile' => "a/$inputFile",
                        'toFile' => "b/$inputFile",
                    ])))->diff($input, $output);
                    $diff = Console::getStdoutTarget()
                                ->getFormatter()
                                ->formatDiff($diff);
                } else {
                    $diff = $inputFile . "\n";
                }
                Console::clearProgress();
                print $diff;
                continue;
            }

            $outFile = $out[$key] ?? '-';
            if ($outFile === '-') {
                Console::clearProgress();
                print $output;
                // If output is being written to a TTY, add a blank line between
                // files and messages
                if (stream_isatty(\STDOUT)) {
                    Console::printTty('');
                }
                continue;
            }

            if (!File::same($file, $outFile)) {
                $input = is_file($outFile) ? File::getContents($outFile) : null;
            }

            if ($input !== null && $input === $output) {
                if ($this->Verbose) {
                    Console::log('Already formatted:', $outFile);
                    $unchanged++;
                }
                continue;
            }

            if ($this->Quiet < 1) {
                Console::log('Replacing', $outFile);
            }
            File::writeContents($outFile, $output);
            $replaced++;
        }

        $invalid = 0;
        if ($errors) {
            foreach ($errors as $error) {
                if (!$error instanceof Throwable) {
                    Console::warn(Console::escape($error));
                    continue;
                }
                $invalid++;
                $prev = $error->getPrevious();
                if ($prev) {
                    Console::error(Console::escape($error->getMessage() . ': ' . $prev->getMessage()));
                    continue;
                }
                Console::error(Console::escape($error->getMessage()));
            }
        }

        if ($this->Diff !== null || $this->Check) {
            if ($this->Quiet < 2) {
                if (($this->Diff !== null && $replaced && stream_isatty(\STDOUT))) {
                    Console::printTty('');
                } elseif ($errors) {
                    Console::printOut('');
                }
                $format = 'Found ' . Arr::implode(' and ', [
                    $invalid ? Inflect::format($invalid, '{{#}} invalid {{#:file}}') : null,
                    $replaced ? Inflect::format($replaced, '{{#}} unformatted {{#:file}}') : null,
                    !$replaced && !$invalid ? 'no unformatted files' : null,
                ]) . ' after checking {{#}} {{#:file}}';
                $this->printSummary($count, $format);
            }

            return $invalid ? 4 : ($replaced ? 8 : 0);
        }

        if ($this->Quiet < 2) {
            if ($replaced || $unchanged || $errors) {
                Console::printOut('');
            }
            $this->printSummary(
                $count,
                $replaced
                    ? 'Replaced %d of {{#}} {{#:file}}'
                    : 'Formatted {{#}} {{#:file}}',
                'successfully',
                false,
                Console::getErrorCount() || Console::getWarningCount(),
                $replaced,
            );
        }
    }

    /**
     * @param mixed ...$values
     */
    private function printSummary(
        int $count,
        string $format,
        string $successText = '',
        bool $withoutErrorCount = true,
        bool $withStandardMessageType = true,
        ...$values
    ): void {
        Console::summary(
            Inflect::format($count, $format, ...$values),
            $successText,
            true,
            $withoutErrorCount,
            $withStandardMessageType,
        );
    }

    /**
     * @param array<string,array<string|int|bool|float>|string|int|bool|float|null> $values
     */
    private function getConfig(array $values): self
    {
        $defaults = $this->getDefaultSchemaOptionValues();

        /** @var self */
        $clone = Get::copy($this);
        $clone->applyOptionValues($values + $defaults, true, true, true);
        $clone->applyOptionValues($values, true, true, true, true, true);
        return $clone;
    }

    /**
     * @return array<string,array<string|int|bool|float>|string|int|bool|float|null>
     */
    private function getDefaultFormattingOptionValues(): array
    {
        return array_diff_key(
            $this->getDefaultSchemaOptionValues(),
            self::SRC_OPTION_INDEX,
        );
    }

    /**
     * @return array<string,array<string|int|bool|float>|string|int|bool|float|null>
     */
    private function getDefaultSchemaOptionValues(): array
    {
        return $this->DefaultSchemaOptionValues ??=
            $this->getDefaultOptionValues(true);
    }

    /**
     * @return array<string,array<string|int|bool|float>|string|int|bool|float|null>
     */
    private function getFormattingConfigValues(string $filename, bool $pristine = false): array
    {
        return array_diff_key(
            $this->getConfigValues($filename, $pristine),
            self::SRC_OPTION_INDEX,
        );
    }

    /**
     * @return array<string,array<string|int|bool|float>|string|int|bool|float|null>
     */
    private function getConfigValues(string $filename, bool $pristine = false): array
    {
        Console::debug('Reading configuration file:', $filename);

        $json = File::getContents($filename);

        if ($json === '') {
            throw new InvalidConfigurationException(sprintf(
                'Empty configuration file: %s',
                $filename,
            ));
        }

        try {
            $config = Json::parseObjectAsArray($json);
        } catch (JsonException $ex) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid JSON in configuration file: %s (%s)',
                $filename,
                $ex->getMessage(),
            ), $ex);
        }

        if (!is_array($config) || !$this->checkOptionValues($config)) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid configuration file: %s',
                $filename,
            ));
        }

        if ($pristine) {
            return $config;
        }

        if ($this->Tight) {
            $config['tight'] = true;
        }

        return $config;
    }

    private function getConfigFile(string $dir): ?string
    {
        Console::debug('Looking for a configuration file:', $dir);

        $dir = File::getCleanDir($dir);
        $found = [];
        foreach (self::CONFIG_FILE_NAME as $file) {
            $file = $dir . '/' . $file;
            if (is_file($file)) {
                $found[] = $file;
            }
        }

        if (count($found) > 1) {
            throw new InvalidConfigurationException(sprintf(
                'Too many configuration files: %s',
                implode(' ', $found),
            ));
        }

        return $found ? $found[0] : null;
    }

    /**
     * @param array<string,string> $files
     * @param-out array<string,string> $files
     * @param array<string,string> $dirs
     * @param-out array<string,string> $dirs
     */
    private function addDir(string $dir, array &$files, array &$dirs): void
    {
        if ($this->Debug) {
            Console::debug(sprintf(
                '(#%d) Searching for files to format:',
                spl_object_id($this),
            ), Json::prettyPrint([
                'in' => $dir,
                'excludeRegex' => $this->ExcludeRegex,
                'includeRegex' => $this->IncludeRegex,
                'includeIfPhpRegex' => $this->IncludeIfPhpRegex,
            ]));
        }

        $dir = File::getCleanDir($dir);
        $finder = File::find()
                      ->in($dir)
                      ->exclude($this->ExcludeRegex)
                      ->include($this->IncludeRegex);

        if ($this->IncludeIfPhpRegex !== null) {
            $finder = $finder->include(
                fn(SplFileInfo $file, string $path) =>
                    Regex::match($this->IncludeIfPhpRegex, $path)
                    && File::hasPhp((string) $file)
            );
        }

        foreach ($finder as $file) {
            $this->addFile($file, $files, $dirs);
        }
    }

    /**
     * @param SplFileInfo|string $file
     * @param array<string,string> $files
     * @param-out array<string,string> $files
     * @param array<string,string> $dirs
     * @param-out array<string,string> $dirs
     */
    private function addFile($file, array &$files, array &$dirs): void
    {
        $key = File::getIdentifier((string) $file);

        if (isset($files[$key])) {
            Console::debug(sprintf('Skipping (already seen): %s', (string) $file));
            return;
        }

        $files[$key] = (string) $file;

        if ($this->IgnoreConfigFiles) {
            return;
        }

        $dir = is_string($file)
            ? dirname($file)
            : (string) $file->getPathInfo();
        $dirs[$dir] = $dir;
    }

    /**
     * @param class-string<Throwable> $exception
     */
    private function validateOptions(
        string $exception = CliInvalidArgumentsException::class,
        ?string $configFile = null
    ): void {
        $throw = fn(string $message, string ...$names) =>
                     $this->throwInvalidOptionsException(
                         $exception, $configFile, $message, ...$names
                     );

        if ($this->OperatorsFirst && $this->OperatorsLast) {
            $throw(
                '%s and %s cannot both be given',
                '--operators-first',
                '--operators-last',
            );
        }

        if ($this->Tight && in_array(
            $key = Arr::keyOf(self::DISABLE_MAP, DeclarationSpacing::class),
            $this->Disable,
            true,
        )) {
            $throw(
                '%s and %s=%s cannot both be given',
                '--tight',
                '--disable',
                $key,
            );
        }

        if ($this->optionHasArgument('sort-imports-by') && (
            in_array(
                $key = Arr::keyOf(self::DISABLE_MAP, SortImports::class),
                $this->Disable,
                true,
            ) || $this->NoSortImports
        )) {
            $throw(
                '%s and %s/%s=%s cannot both be given',
                '--sort-imports-by',
                '--no-sort-imports',
                '--disable',
                $key,
            );
        }
    }

    /**
     * @param class-string<Throwable> $exception
     * @return never
     */
    private function throwInvalidOptionsException(
        string $exception,
        ?string $configFile,
        string $format,
        string ...$values
    ): void {
        if ($configFile !== null) {
            foreach ($values as &$value) {
                if (substr($value, 0, 2) === '--') {
                    $value = Str::camel($value);
                }
            }
            $format .= ' in %s';
            $values[] = $configFile;
        }

        throw new $exception(sprintf($format, ...$values));
    }

    /**
     * @param class-string<Throwable> $exception
     */
    private function getFormatter(string $exception = CliInvalidArgumentsException::class): Formatter
    {
        try {
            return $this->doGetFormatter();
            // @codeCoverageIgnoreStart
        } catch (InvalidFormatterException $ex) {
            throw new $exception($ex->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    private function doGetFormatter(): Formatter
    {
        $flags = 0;
        if ($this->Debug) {
            $flags |= FormatterFlag::DEBUG;
            if ($this->LogProgress) {
                $flags |= FormatterFlag::LOG_PROGRESS;
            }
        }
        if ($this->Quiet < 3) {
            $flags |= FormatterFlag::DETECT_PROBLEMS;
        }

        if ($this->Debug) {
            Console::debug(sprintf(
                '(#%d) Generating a formatter:',
                spl_object_id($this),
            ), Json::prettyPrint([
                'flags' => $flags,
                'tabs' => $this->Tabs,
                'spaces' => $this->Spaces,
                'eol' => $this->Eol,
                'disable' => $this->Disable,
                'enable' => $this->Enable,
                'oneTrueBraceStyle' => $this->OneTrueBraceStyle,
                'operatorsFirst' => $this->OperatorsFirst,
                'operatorsLast' => $this->OperatorsLast,
                'tight' => $this->Tight,
                'ignoreNewlines' => $this->IgnoreNewlines,
                'noSimplifyStrings' => $this->NoSimplifyStrings,
                'noSimplifyNumbers' => $this->NoSimplifyNumbers,
                'heredocIndent' => $this->HeredocIndent,
                'sortImportsBy' => $this->SortImportsBy,
                'noSortImports' => $this->NoSortImports,
                'psr12' => $this->Psr12,
                'preset' => $this->Preset,
            ]));
        }

        if ($this->Preset !== null) {
            /** @var Formatter */
            $formatter = self::PRESET_MAP[$this->Preset]::getFormatter($flags);
            if ($this->Tight) {
                if ($formatter->Enabled[DeclarationSpacing::class] ?? false) {
                    $formatter = $formatter->withTightDeclarationSpacing();
                } else {
                    Console::warn(sprintf(
                        '%s preset disabled tight declaration spacing',
                        $this->Preset,
                    ), null, null, false);
                }
            }
            if ($this->Psr12) {
                $formatter = $formatter->withPsr12();
            }
            return $formatter;
        }

        $disable = $this->Disable;
        if ($this->IgnoreNewlines) {
            $disable[] = 'preserve-newlines';
        }
        if ($this->NoSimplifyStrings) {
            $disable[] = 'simplify-strings';
        }
        if ($this->NoSimplifyNumbers) {
            $disable[] = 'simplify-numbers';
        }
        if ($this->NoSortImports) {
            $disable[] = 'sort-imports';
        }

        $disable = array_values(array_intersect_key(self::DISABLE_MAP, array_flip($disable)));
        $enable = array_values(array_intersect_key(self::ENABLE_MAP, array_flip($this->Enable)));

        $f = (new FormatterBuilder())
                 ->insertSpaces(!$this->Tabs)
                 ->tabSize($this->Tabs ?: $this->Spaces ?: 4)
                 ->disable($disable)
                 ->enable($enable)
                 ->flags($flags)
                 ->tokenTypeIndex(new TokenTypeIndex($this->OperatorsFirst, $this->OperatorsLast))
                 ->preferredEol(self::EOL_MAP[$this->Eol])
                 ->preserveEol($this->Eol === 'auto')
                 ->heredocIndent(self::HEREDOC_INDENT_MAP[$this->HeredocIndent])
                 ->importSortOrder(self::IMPORT_SORT_ORDER_MAP[$this->SortImportsBy])
                 ->oneTrueBraceStyle($this->OneTrueBraceStyle)
                 ->tightDeclarationSpacing($this->Tight)
                 ->psr12($this->Psr12)
                 ->build();

        return $f;
    }

    /**
     * @param Token[]|null $tokens
     * @param array<string,string>|null $log
     * @param mixed $data
     */
    private function maybeDumpDebugOutput(
        string $input,
        ?string $output,
        ?array $tokens,
        ?array $log,
        $data
    ): void {
        if ($this->DebugDirectory === null) {
            return;
        }

        Profile::startTimer(__METHOD__);

        $files = [];
        $i = 0;
        $last = null;
        foreach ((array) $log as $after => $out) {
            // Only dump output logged after something changed
            if ($i++ && $out === $last) {
                continue;
            }
            $file = sprintf('%s/%03d-%s.php', self::PROGRESS_LOG_DIR, $i, $after);
            $files[$file] = $out;
            $last = $out;
        }

        // Either empty or completely remove the progress log directory
        $dir = "{$this->DebugDirectory}/" . self::PROGRESS_LOG_DIR;
        if ($files) {
            File::createDir($dir);
            File::pruneDir($dir);
        } elseif (is_dir($dir)) {
            File::pruneDir($dir, true);
        }

        $files += [
            'input.php' => $input,
            'output.php' => $output,
            'tokens.json' => $tokens,
            'data.out' => is_string($data) ? $data : null,
            'data.json' => is_string($data) ? null : $data,
        ];

        foreach ($files as $file => $out) {
            $file = "{$this->DebugDirectory}/{$file}";
            File::delete($file);
            if ($out === null) {
                continue;
            }
            if (!is_string($out)) {
                $out = Json::prettyPrint(
                    $out,
                    \JSON_FORCE_OBJECT | \JSON_INVALID_UTF8_IGNORE
                ) . \PHP_EOL;
            }
            File::writeContents($file, $out);
        }

        Profile::stopTimer(__METHOD__);
    }
}
