<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Command;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility as Visibility;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Console\Catalog\ConsoleLevel;
use Lkrms\Facade\Console;
use Lkrms\Facade\Profile;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Preset;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidConfigurationException;
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
use Lkrms\PrettyPHP\Rule\BlankLineBeforeReturn;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Rule\NormaliseNumbers;
use Lkrms\PrettyPHP\Rule\NormaliseStrings;
use Lkrms\PrettyPHP\Rule\PreserveLineBreaks;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Rule\StrictExpressions;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Get;
use Lkrms\Utility\Json;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Str;
use Lkrms\Utility\Sys;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use JsonException;
use SplFileInfo;
use Throwable;

/**
 * Provides pretty-php's command-line interface
 */
final class FormatPhp extends CliCommand
{
    private const DISABLE_MAP = [
        'sort-imports' => SortImports::class,
        'move-comments' => MoveComments::class,
        'simplify-strings' => NormaliseStrings::class,
        'simplify-numbers' => NormaliseNumbers::class,
        'preserve-newlines' => PreserveLineBreaks::class,
        'declaration-spacing' => DeclarationSpacing::class,
    ];

    private const ENABLE_MAP = [
        'align-comments' => AlignComments::class,
        'align-chains' => AlignChains::class,
        'align-fn' => AlignArrowFunctions::class,
        'align-ternary' => AlignTernaryOperators::class,
        'align-data' => AlignData::class,
        'align-lists' => AlignLists::class,
        'blank-before-return' => BlankLineBeforeReturn::class,
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

    /**
     * @var string[]
     */
    private array $InputFiles = [];

    private string $IncludeRegex = '';

    private string $ExcludeRegex = '';

    private ?string $IncludeIfPhpRegex = null;

    private ?int $Tabs = null;

    private ?int $Spaces = null;

    private string $Eol = '';

    /**
     * @var string[]
     */
    private array $Disable = [];

    /**
     * @var string[]
     */
    private array $Enable = [];

    private bool $OneTrueBraceStyle = false;

    private bool $OperatorsFirst = false;

    private bool $OperatorsLast = false;

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

    /**
     * @var string[]
     */
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

    /**
     * @var array<string,Formatter>
     */
    private array $FormatterByDir;

    private Formatter $DefaultFormatter;

    /**
     * @var array<string,array<string|int|bool>|string|int|bool|null>
     */
    private array $DefaultSchemaOptionValues;

    private bool $Debug;

    /**
     * @codeCoverageIgnore
     */
    public function description(): string
    {
        return 'Format a PHP file';
    }

    /**
     * @inheritDoc
     */
    protected function getOptionList(): array
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

Formatting options other than `-N/--ignore-newlines` are ignored when a preset
is applied.
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

Settings in <file> override formatting options given on the command line, and
any configuration files that would usually apply to the input are ignored.

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
files in *\<directory>/{}* that represent changes applied by enabled rules.
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

- `-qq`: do not print a summary of files formatted and replaced on exit.
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
options other than `-N/--ignore-newlines` are ignored.

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
`{{program}}` returns 0 when formatting succeeds, 1 when invalid arguments are
given, 2 when invalid configuration files are found, and 4 when one or more
input files cannot be parsed. When `--diff` or `--check` are given,
`{{program}}` returns 0 when the input is already formatted and 8 when
formatting is required.
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
        // `--preset` and `--psr12`
        if (isset($values['preset'])) {
            return array_intersect_key($values, self::SRC_OPTION_INDEX + [
                'preset' => true,
                'psr12' => true,
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

        if ($this->DebugDirectory !== null) {
            File::createDir($this->DebugDirectory);
            $this->DebugDirectory = File::realpath($this->DebugDirectory);
            Env::debug(true);
            $this->App->logOutput();
            $this->Debug = true;
        } else {
            $this->Debug = Env::debug();
        }

        if ($this->ReportTimers) {
            $this->App->registerShutdownReport(ConsoleLevel::NOTICE);
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot both be given');
        }

        $this->validateOptions();

        if ($this->ConfigFile !== null) {
            $this->IgnoreConfigFiles = true;
            $config = $this->getFormattingConfigValues($this->ConfigFile);
            $defaults = $this->getDefaultFormattingOptionValues();
            // 1. Set the value of every formatting option
            $this->applyOptionValues($config + $defaults, true, true, true);
            // 2. Set the value of configured options as if they had been given
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
            !$this->IgnoreConfigFiles &&
            !$this->PrintConfig &&
            ($this->InputFiles || $this->StdinFilename === null)
        ) {
            // Get files and directories to format from the current directory's
            // configuration file (if there are no paths on the command line and
            // a configuration file exists), or from the configuration files of
            // any directories on the command line (if they exist)
            foreach ($this->InputFiles ?: ['.'] as $path) {
                if (!is_dir($path)) {
                    $this->addFile($path, $in, $dirs);
                    continue;
                }

                $configFile = $this->getConfigFile($path);
                $configValues = $configFile === null
                    ? null
                    : $this->getConfigValues($configFile);

                if ($configValues === null) {
                    if (!$this->InputFiles) {
                        break;
                    }
                    $dirCount++;
                    $this->addDir($path, $in, $dirs);
                    continue;
                }

                $dirCount++;

                $config = $this->getConfig($configValues);
                $config->validateOptions(InvalidConfigurationException::class, $configFile);
                if ($this->Debug) {
                    Console::debug(sprintf(
                        'Applied options from %s to instance #%d:',
                        $configFile,
                        spl_object_id($config),
                    ), Json::prettyPrint($configValues));
                }
                $dir = dirname($configFile);
                $this->FormatterByDir[$dir] = $config->getFormatter();

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
                    $path = $dir . '/' . $path;
                    if ($path === './.') {
                        $path = '.';
                    }
                    if (is_file($path)) {
                        $config->addFile($path, $in, $dirs);
                        continue;
                    }
                    if (is_dir($path)) {
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
                $this->addDir($path, $in, $dirs);
            }
        }

        if (
            !$in &&
            !$dirCount &&
            !$this->InputFiles &&
            !$this->PrintConfig &&
            $this->StdinFilename === null &&
            stream_isatty(\STDIN)
        ) {
            throw new CliInvalidArgumentsException('<path> required when input is a TTY');
        }

        $in = array_values($in);
        $out = $this->OutputFiles;

        if ((!$in && !$dirCount && !$this->InputFiles) || $in === ['-']) {
            $in = ['php://stdin'];
            $out = ['-'];
            if ($this->StdinFilename !== null && !$this->IgnoreConfigFiles) {
                $dir = dirname($this->StdinFilename);
                $dirs[$dir] = $dir;
            }
        } elseif (
            $out &&
            $out !== ['-'] &&
            ($dirCount || count($out) !== count($in))
        ) {
            throw new CliInvalidArgumentsException(
                '--output is required once per input file'
                . ($dirCount ? ' and cannot be given with directories' : '')
            );
        } elseif (!$out) {
            $out = $in;
        }

        if (
            $out === ['-'] ||
            $this->Diff ||
            $this->Check ||
            $this->PrintConfig
        ) {
            Console::registerStderrTarget(true);
        }

        if ($this->PrintConfig) {
            $values = $this->getOptionValues(true, true);
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
                    $formatter = $config->getFormatter();
                    break;
                }
                if (
                    is_dir($dir . '/.git') ||
                    is_dir($dir . '/.hg') ||
                    is_dir($dir . '/.svn')
                ) {
                    Console::debug('No configuration file found in project:', $dir);
                    break;
                }
                if ($dir === '.') {
                    $dir = Sys::getCwd();
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
        $errors = [];
        foreach ($in as $key => $file) {
            $i++;

            $inputFile = Str::coalesce(
                $file === 'php://stdin' ? $this->StdinFilename : null,
                $file,
            );

            if (
                $this->Quiet < 4 &&
                ($file !== 'php://stdin' || !stream_isatty(\STDIN))
            ) {
                Console::logProgress(sprintf(
                    'Formatting %d of %d:',
                    $i,
                    $count,
                ), $inputFile);
            }

            $dir = dirname($inputFile);
            $formatter = $this->FormatterByDir[$dir]
                ??= $this->DefaultFormatter
                ??= $this->getFormatter();
            $input = File::getContents($file);
            Profile::startTimer($inputFile, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    null,
                    $inputFile,
                    $this->Fast
                );
            } catch (InvalidSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(4);
                $errors[] = $inputFile;
                continue;
            } catch (FormatterException $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getLog(), $ex->getData());
                throw $ex;
            } catch (Throwable $ex) {
                Console::error('Unable to format:', $inputFile);
                $this->maybeDumpDebugOutput($input, null, $formatter->Tokens, $formatter->Log, (string) $ex);
                throw $ex;
            } finally {
                Profile::stopTimer($inputFile, 'file');
            }

            if ($i === $count) {
                $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens, $formatter->Log, null);
            }

            if ($this->Check) {
                if ($input === $output) {
                    continue;
                }
                if ($this->Quiet < 2) {
                    Console::error('Input requires formatting');
                }

                return 8;
            }

            if ($this->Diff) {
                if ($input === $output) {
                    if ($this->Verbose) {
                        Console::log('Already formatted:', $inputFile);
                    }
                    continue;
                }
                Console::maybeClearLine();
                switch ($this->Diff) {
                    case 'name-only':
                        printf("%s\n", $inputFile);
                        break;
                    case 'unified':
                        $formatter = Console::getStdoutTarget()->getFormatter();
                        $diff = (new Differ(new StrictUnifiedDiffOutputBuilder([
                            'fromFile' => "a/$inputFile",
                            'toFile' => "b/$inputFile",
                        ])))->diff($input, $output);
                        print $formatter->formatDiff($diff);
                        if ($this->Quiet < 1) {
                            Console::log('Would replace', $inputFile);
                        }
                        break;
                }
                $replaced++;
                continue;
            }

            $outFile = $out[$key] ?? '-';
            if ($outFile === '-') {
                Console::maybeClearLine();
                print $output;
                if (stream_isatty(\STDOUT)) {
                    Console::tty('');
                }
                continue;
            }

            if (!File::same($file, $outFile)) {
                $input = is_file($outFile) ? File::getContents($outFile) : null;
            }

            if ($input !== null && $input === $output) {
                if ($this->Verbose) {
                    Console::log('Already formatted:', $outFile);
                }
                continue;
            }

            if ($this->Quiet < 1) {
                Console::log('Replacing', $outFile);
            }
            File::putContents($outFile, $output);
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
            if ($this->Quiet < 2) {
                Console::log(sprintf(
                    '%d %s would be left unchanged',
                    $count,
                    Convert::plural($count, 'file')
                ));
            }

            return;
        }

        if ($this->Diff) {
            if ($this->Quiet < 2) {
                if ($replaced) {
                    Console::out('', ConsoleLevel::INFO);
                }
                Console::log(sprintf(
                    $replaced
                        ? '%1$d of %2$d %3$s %4$s formatting'
                        : '%2$d %3$s would be left unchanged',
                    $replaced,
                    $count,
                    Convert::plural($count, 'file'),
                    Convert::plural($count, 'requires', 'require')
                ));
            }

            return $replaced ? 8 : 0;
        }

        if ($this->Quiet < 2) {
            Console::summary(sprintf(
                $replaced ? 'Replaced %1$d of %2$d %3$s' : 'Formatted %2$d %3$s',
                $replaced,
                $count,
                Convert::plural($count, 'file')
            ), 'successfully');
        }
    }

    /**
     * @param mixed[] $values
     */
    private function getConfig(array $values): self
    {
        $defaults = $this->getDefaultSchemaOptionValues();

        /** @var self */
        $clone = Get::copy($this);
        $clone->applyOptionValues($values + $defaults, true, true, true);
        return $clone;
    }

    /**
     * @return array<string,array<string|int|bool>|string|int|bool|null>
     */
    private function getDefaultFormattingOptionValues(): array
    {
        return array_diff_key(
            $this->getDefaultSchemaOptionValues(),
            self::SRC_OPTION_INDEX,
        );
    }

    /**
     * @return array<string,array<string|int|bool>|string|int|bool|null>
     */
    private function getDefaultSchemaOptionValues(): array
    {
        return $this->DefaultSchemaOptionValues
            ??= $this->getDefaultOptionValues(true);
    }

    /**
     * @return mixed[]
     */
    private function getFormattingConfigValues(string $filename): array
    {
        return array_diff_key(
            $this->getConfigValues($filename),
            self::SRC_OPTION_INDEX,
        );
    }

    /**
     * @return mixed[]
     */
    private function getConfigValues(string $filename): array
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

        if (!is_array($config)) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid configuration file: %s',
                $filename,
            ));
        }

        return $config;
    }

    private function getConfigFile(string $dir): ?string
    {
        Console::debug('Looking for a configuration file:', $dir);

        $dir = File::dir($dir);
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
     * @param array<string,string> $dirs
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

        $dir = File::dir($dir);
        $finder = File::find()
                      ->in($dir)
                      ->exclude($this->ExcludeRegex)
                      ->include($this->IncludeRegex);

        if ($this->IncludeIfPhpRegex !== null) {
            $finder = $finder->include(
                fn(SplFileInfo $file, string $path) =>
                    Pcre::match($this->IncludeIfPhpRegex, $path) &&
                    File::isPhp((string) $file)
            );
        }

        foreach ($finder as $file) {
            $this->addFile($file, $files, $dirs);
        }
    }

    /**
     * @param SplFileInfo|string $file
     * @param array<string,string> $files
     * @param array<string,string> $dirs
     */
    private function addFile($file, array &$files, array &$dirs): void
    {
        $key = $this->getFileKey($file);

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
     * @param SplFileInfo|string $file
     */
    private function getFileKey($file): string
    {
        $stat = File::stat((string) $file);
        return $stat['dev'] . "\0" . $stat['ino'];
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

        if (
            $this->SortImportsBy &&
            ($this->NoSortImports || in_array('sort-imports', $this->Disable, true))
        ) {
            $throw(
                '%s and %s/%s=sort-imports cannot both be given',
                '--sort-imports-by',
                '--no-sort-imports',
                '--disable',
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
        string $message,
        string ...$names
    ): void {
        if ($configFile !== null) {
            foreach ($names as &$name) {
                $name = Str::toCamelCase($name);
            }
            $message .= ' in %s';
            $names[] = $configFile;
        }

        throw new $exception(sprintf($message, ...$names));
    }

    private function getFormatter(): Formatter
    {
        $flags = 0;
        if ($this->Quiet < 3) {
            $flags |= FormatterFlag::REPORT_CODE_PROBLEMS;
        }
        if ($this->LogProgress) {
            $flags |= FormatterFlag::LOG_PROGRESS;
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
            return $this->Psr12
                ? $formatter->withPsr12()
                : $formatter;
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

        if ($this->OperatorsFirst) {
            $tokenTypeIndex = (new TokenTypeIndex())->withLeadingOperators();
        } elseif ($this->OperatorsLast) {
            $tokenTypeIndex = (new TokenTypeIndex())->withTrailingOperators();
        } else {
            $tokenTypeIndex = new TokenTypeIndex();
        }

        $f = (new FormatterBuilder())
                 ->insertSpaces(!$this->Tabs)
                 ->tabSize($this->Tabs ?: $this->Spaces ?: 4)
                 ->disable($disable)
                 ->enable($enable)
                 ->flags($flags)
                 ->preferredEol(self::EOL_MAP[$this->Eol])
                 ->preserveEol($this->Eol === 'auto')
                 ->heredocIndent(self::HEREDOC_INDENT_MAP[$this->HeredocIndent])
                 ->importSortOrder(self::IMPORT_SORT_ORDER_MAP[$this->SortImportsBy])
                 ->oneTrueBraceStyle($this->OneTrueBraceStyle)
                 ->psr12($this->Psr12)
                 ->go();

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
        } else {
            File::deleteDir($dir, true);
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
            File::putContents($file, $out);
        }

        Profile::stopTimer(__METHOD__);
    }
}
