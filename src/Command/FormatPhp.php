<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Command;

use Lkrms\Cli\Catalog\CliHelpSectionName;
use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\Catalog\CliOptionValueType;
use Lkrms\Cli\Catalog\CliOptionValueUnknownPolicy;
use Lkrms\Cli\Catalog\CliOptionVisibility as Visibility;
use Lkrms\Cli\Exception\CliInvalidArgumentsException;
use Lkrms\Cli\CliApplication;
use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Console\Catalog\ConsoleLevel;
use Lkrms\Exception\UnexpectedValueException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Profile;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidConfigurationException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Rule\Contract\Rule;
use Lkrms\PrettyPHP\Rule\Preset\Drupal;
use Lkrms\PrettyPHP\Rule\Preset\Laravel;
use Lkrms\PrettyPHP\Rule\Preset\Symfony;
use Lkrms\PrettyPHP\Rule\Preset\WordPress;
use Lkrms\PrettyPHP\Rule\Support\WordPressTokenTypeIndex;
use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Rule\BlankLineBeforeReturn;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
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
use Lkrms\Utility\File;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Sys;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use SplFileInfo;
use Throwable;

/**
 * Provides pretty-php's command-line interface
 */
class FormatPhp extends CliCommand
{
    private const DISABLE_MAP = [
        'sort-imports' => SortImports::class,
        'move-comments' => MoveComments::class,
        'simplify-strings' => NormaliseStrings::class,
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

    private const INTERNAL_OPTION_MAP = [
        'spaces-beside-code' => 'SpacesBesideCode',
        'increase-indent-between-unenclosed-tags' => 'IncreaseIndentBetweenUnenclosedTags',
        'relax-alignment-criteria' => 'RelaxAlignmentCriteria',
        'preset-rules' => 'PresetRules',
        'token-type-index' => 'TokenTypeIndex',
    ];

    private const PRESET_MAP = [
        'drupal' => [
            'space' => 2,
            'disable' => [],
            'enable' => [],
            'one-true-brace-style' => true,
            'heredoc-indent' => 'none',
            '@internal' => [
                'preset-rules' => [
                    Drupal::class,
                ],
            ],
        ],
        'laravel' => [
            'disable' => [],
            'enable' => [
                'align-lists',
                'blank-before-return',
            ],
            'heredoc-indent' => 'none',
            '@internal' => [
                'preset-rules' => [
                    Laravel::class,
                ],
            ],
        ],
        'symfony' => [
            'disable' => [],
            'enable' => [
                'blank-before-return',
            ],
            'operators-first' => true,
            'heredoc-indent' => 'none',
            '@internal' => [
                'preset-rules' => [
                    Symfony::class,
                ],
            ],
        ],
        'wordpress' => [
            'tab' => 4,
            'disable' => [
                'declaration-spacing',
            ],
            'enable' => [
                'align-data',
            ],
            'one-true-brace-style' => true,
            '@internal' => [
                'spaces-beside-code' => 1,
                'increase-indent-between-unenclosed-tags' => false,
                'relax-alignment-criteria' => true,
                'preset-rules' => [
                    WordPress::class,
                ],
                'token-type-index' => WordPressTokenTypeIndex::class,
            ],
        ],
    ];

    private const PROGRESS_LOG_DIR = 'progress-log';

    /**
     * @var string[]|null
     */
    private ?array $InputFiles;

    private ?string $IncludeRegex;

    private ?string $ExcludeRegex;

    private ?string $IncludeIfPhpRegex;

    private ?int $Tabs;

    private ?int $Spaces;

    private ?string $Eol;

    /**
     * @var string[]|null
     */
    private ?array $Disable;

    /**
     * @var string[]|null
     */
    private ?array $Enable;

    private ?bool $OneTrueBraceStyle;

    private ?bool $OperatorsFirst;

    private ?bool $OperatorsLast;

    private ?bool $IgnoreNewlines;

    private ?bool $NoSimplifyStrings;

    /**
     * @var string|null
     */
    protected $HeredocIndent;

    /**
     * @var string|null
     */
    protected $SortImportsBy;

    private ?bool $NoSortImports;

    private ?bool $Psr12;

    /**
     * @var string|null
     */
    protected $Preset;

    /**
     * @var string|null
     */
    protected $ConfigFile;

    private ?bool $IgnoreConfigFiles;

    /**
     * @var string[]|null
     */
    protected $OutputFiles;

    /**
     * @var string|null
     */
    protected $Diff;

    private ?bool $Check;

    private ?bool $PrintConfig;

    private ?string $StdinFilename;

    private ?string $DebugDirectory;

    private ?bool $ReportTimers;

    private ?bool $Fast;

    private ?bool $Verbose;

    private ?int $Quiet;

    // --

    /**
     * @var int|null
     */
    private $SpacesBesideCode;

    /**
     * @var bool|null
     */
    private $IncreaseIndentBetweenUnenclosedTags;

    /**
     * @var bool|null
     */
    private $RelaxAlignmentCriteria;

    /**
     * @var array<class-string<Rule>>|null
     */
    private $PresetRules;

    /**
     * @var class-string<TokenTypeIndex>|null
     */
    private $TokenTypeIndex;

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
     * @var array<string,array<string|int>|string|int|bool|null>
     */
    private $DefaultFormattingOptionValues;

    /**
     * @var array<string,array<string|int>|string|int|bool|null>|null
     */
    private $CliFormattingOptionValues;

    /**
     * @var array<string,array<string,array<string|int>|string|int|bool|null>|null>
     */
    private $DirFormattingOptionValues = [];

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
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
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
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS)
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

If combined with `-v/--verbose`, partially formatted code is written to a series
of files in *\<directory>/{}* that represent changes applied by enabled rules.
EOF))
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->App->getTempPath() . '/debug')
                ->visibility(Visibility::ALL_EXCEPT_SYNOPSIS | Visibility::HIDE_DEFAULT)
                ->bindTo($this->DebugDirectory),
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
Only report warnings and errors.

If given twice, warnings are also suppressed. If given three or more times,
TTY-only progress updates are also suppressed.

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
            Arr::spliceByKey($schema['properties'], 'eol', 0, [
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

    protected function run(...$params)
    {
        if ($this->DebugDirectory !== null) {
            File::createDir($this->DebugDirectory);
            $this->DebugDirectory = realpath($this->DebugDirectory) ?: null;
            if (!$this->Env->debug()) {
                $this->Env->debug(true);
            }
            $this->App->logOutput();
        }

        if ($this->ReportTimers) {
            $this->App->registerShutdownReport(ConsoleLevel::NOTICE);
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot both be given');
        }

        if ($this->OperatorsFirst && $this->OperatorsLast) {
            throw new CliInvalidArgumentsException('--operators-first and --operators-last cannot both be given');
        }

        if (
            $this->SortImportsBy &&
            ($this->NoSortImports || in_array('sort-imports', $this->Disable, true))
        ) {
            throw new CliInvalidArgumentsException('--sort-imports-by and --no-sort-imports/--disable=sort-imports cannot both be given');
        }

        if ($this->ConfigFile) {
            $this->IgnoreConfigFiles = true;
            Console::debug('Reading formatting options:', $this->ConfigFile);
            $json = json_decode(file_get_contents($this->ConfigFile), true);
            // To prevent unintended inclusion of default values in
            // --print-config output, apply options as if they were given on the
            // command line, without expanding optional values
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
                !$this->PrintConfig &&
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
                $this->applyFormattingOptionValues(
                    $this->normaliseFormattingOptionValues($json, true)
                );
                $dir = dirname($configFile);
                $this->DirFormattingOptionValues[$dir] =
                    $this->normaliseFormattingOptionValues($json);
                if (!$this->InputFiles) {
                    continue;
                }
                $dirCount++;
                unset($cliInputFiles[$i]);
                // Update any relative paths loaded from the configuration file
                foreach ($this->InputFiles as &$file) {
                    if (File::isAbsolute($file)) {
                        continue;
                    }
                    $file = $dir . '/' . $file;
                    if ($file === './.') {
                        $file = '.';
                    }
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
        if (!$in && !$this->StdinFilename && !$this->PrintConfig && stream_isatty(\STDIN)) {
            throw new CliInvalidArgumentsException('<path> required when input is a TTY');
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
            printf("%s\n", json_encode($this->getFormattingOptionValues(true), \JSON_PRETTY_PRINT));

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
                if (
                    is_dir($dir . '/.git') ||
                    is_dir($dir . '/.hg') ||
                    is_dir($dir . '/.svn')
                ) {
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
                if ($file !== null) {
                    $options = $this->DirFormattingOptionValues[dirname($file)] ?? null;
                }
                $options ??= $this->CliFormattingOptionValues;
                if ($formatter && $options === $lastOptions) {
                    return $formatter;
                }
                Console::debug('New formatter required for:', $file);
                $this->applyFormattingOptionValues($options);
                !$this->Verbose || Console::debug('Applying options:', json_encode($options, \JSON_PRETTY_PRINT));

                $flags = 0;
                if ($this->Quiet < 2) {
                    $flags |= FormatterFlag::REPORT_CODE_PROBLEMS;
                }
                if ($this->Verbose) {
                    $flags |= FormatterFlag::LOG_PROGRESS;
                }

                $tokenTypeIndex =
                    $this->TokenTypeIndex !== null
                        ? [$this->TokenTypeIndex, 'create']()
                        : ($this->OperatorsFirst
                            ? (new TokenTypeIndex())->withLeadingOperators()
                            : ($this->OperatorsLast
                                ? (new TokenTypeIndex())->withTrailingOperators()
                                : new TokenTypeIndex()));

                $f = (new FormatterBuilder())
                         ->insertSpaces(!$this->Tabs)
                         ->tabSize($this->Tabs ?: $this->Spaces ?: 4)
                         ->disable($this->Disable)
                         ->enable($this->Enable)
                         ->flags($flags)
                         ->tokenTypeIndex($tokenTypeIndex)
                         ->preferredEol(self::EOL_MAP[$this->Eol])
                         ->preserveEol($this->Eol === 'auto')
                         ->spacesBesideCode($this->SpacesBesideCode ?? 2)
                         ->heredocIndent(self::HEREDOC_INDENT_MAP[$this->HeredocIndent])
                         ->importSortOrder(self::IMPORT_SORT_ORDER_MAP[$this->SortImportsBy])
                         ->oneTrueBraceStyle($this->OneTrueBraceStyle)
                         ->psr12($this->Psr12)
                         ->with('IncreaseIndentBetweenUnenclosedTags', $this->IncreaseIndentBetweenUnenclosedTags ?? true)
                         ->with('RelaxAlignmentCriteria', $this->RelaxAlignmentCriteria ?? false);

                $lastOptions = $options;

                return $f;
            };

        $i = 0;
        $count = count($in);
        $replaced = 0;
        $errors = [];
        foreach ($in as $key => $file) {
            $inputFile = ($file === 'php://stdin' ? $this->StdinFilename : null) ?: $file;
            if ($this->Quiet < 3 && ($file !== 'php://stdin' || !stream_isatty(\STDIN))) {
                Console::logProgress(sprintf('Formatting %d of %d:', ++$i, $count), $inputFile);
            }
            $input = file_get_contents($file);
            $formatter = $getFormatter($inputFile);
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
                if (!$this->Quiet) {
                    Console::error('Input requires formatting');
                }

                return 8;
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
                        if (!$this->Quiet) {
                            Console::log('Would replace', $inputFile);
                        }
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

            if (!File::is($file, $outFile)) {
                $input = is_file($outFile) ? file_get_contents($outFile) : null;
            }

            if ($input !== null && $input === $output) {
                !$this->Verbose || Console::log('Already formatted:', $outFile);
                continue;
            }

            if (!$this->Quiet) {
                Console::log('Replacing', $outFile);
            }
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
            if (!$this->Quiet) {
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

        if (!$this->Quiet) {
            Console::summary(sprintf(
                $replaced ? 'Replaced %1$d of %2$d %3$s' : 'Formatted %2$d %3$s',
                $replaced,
                $count,
                Convert::plural($count, 'file')
            ), 'successfully');
        }
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
                    throw new InvalidConfigurationException(sprintf('Too many configuration files: %s', $dir));
                }
                $found = $file;
            }
        }

        return $found ?? null;
    }

    /**
     * @return array<string,array<string|int>|string|int|bool|null>
     */
    private function getFormattingOptionValues(bool $global, bool $internal = false): array
    {
        $options = $this->getOptionValues(true, [Convert::class, 'toCamelCase']);
        if ($this->Tabs || $this->Spaces) {
            $options['insertSpaces'] = !$this->Tabs;
            $options['tabSize'] = $this->Tabs ?: $this->Spaces ?: 4;
        }
        $options = array_intersect_key(
            $options,
            $this->getFormattingOptionNames($global)
        );
        // If a preset is enabled, remove every other formatting option
        if ($this->Preset) {
            $options = array_diff_key(
                $options,
                array_diff_key(
                    $this->getFormattingOptionNames(false),
                    ['preset' => null, 'psr12' => null]
                )
            );
        }
        if ($internal) {
            foreach (self::INTERNAL_OPTION_MAP as $name => $property) {
                $options['@internal'][$name] = $this->{$property};
            }
        }

        return $options;
    }

    /**
     * @param array<string,array<string|int>|string|int|bool|null> $values
     * @return array<string,array<string|int>|string|int|bool|null>
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
     * @param array<string,array<string|int>|string|int|bool|null>|null $values
     * @return $this
     */
    private function applyFormattingOptionValues(?array $values = null, bool $asArguments = false)
    {
        if ($values !== null) {
            $this->applyOptionValues($values, false, false, $asArguments);
            if ($internal = $values['@internal'] ?? null) {
                /** @var array<array<class-string<Rule>>|class-string<TokenTypeIndex>|int|bool|null> $internal */
                foreach ($internal as $name => $value) {
                    $property = self::INTERNAL_OPTION_MAP[$name] ?? null;
                    if (!$property) {
                        throw new UnexpectedValueException(sprintf('@internal option not recognised: %s', $name));
                    }
                    $this->{$property} = $value;
                }
            }
        }

        if ($this->Preset && array_key_exists('preset', $values)) {
            return $this->applyFormattingOptionValues(self::PRESET_MAP[$this->Preset]);
        }

        if ($this->IgnoreNewlines) {
            $this->Disable[] = 'preserve-newlines';
        }
        if ($this->NoSimplifyStrings) {
            $this->Disable[] = 'simplify-strings';
        }
        if ($this->NoSortImports) {
            $this->Disable[] = 'sort-imports';
        }

        foreach (Formatter::INCOMPATIBLE_RULES as $rules) {
            $rules = array_keys(array_intersect(self::ENABLE_MAP, $rules));
            // If multiple rules from this group have been enabled, remove all
            // but the last
            $rules = array_intersect($this->Enable, $rules);
            if (count($rules) > 1) {
                array_pop($rules);
                $this->Enable = array_diff_key($this->Enable, $rules);
            }
        }

        $this->Disable = array_values(array_intersect_key(self::DISABLE_MAP, array_flip($this->Disable)));
        $this->Enable = array_values(array_intersect_key(self::ENABLE_MAP, array_flip($this->Enable)));
        if ($this->PresetRules) {
            array_push($this->Enable, ...$this->PresetRules);
        }

        return $this;
    }

    /**
     * @return array<string,null>
     */
    private function getFormattingOptionNames(bool $global, bool $kebabCase = false): array
    {
        $names = $global
            ? ['src', 'include', 'exclude', 'includeIfPhp']
            : [];
        $names = [
            ...$names,
            'eol',
            'disable',
            'enable',
            'oneTrueBraceStyle',
            'operatorsFirst',
            'operatorsLast',
            'noSimplifyStrings',
            'heredocIndent',
            'sortImportsBy',
            'noSortImports',
            'psr12',
            'preset',
        ];
        $names = $kebabCase ? [
            ...$names,
            'tab',
            'space'
        ] : [
            ...$names,
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
        if (!$paths) {
            return;
        }

        Console::debug('Expanding paths:', implode(' ', $paths));

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

            $iterator = File::find()
                            ->in($path)
                            ->exclude($this->ExcludeRegex)
                            ->include($this->IncludeRegex);

            if ($this->IncludeIfPhpRegex !== null) {
                $iterator = $iterator
                    ->include(
                        fn(SplFileInfo $file, string $path) =>
                            Pcre::match($this->IncludeIfPhpRegex, $path) &&
                            File::isPhp((string) $file)
                    );
            }

            foreach ($iterator as $file) {
                $addFile($file);
            }
        }

        $files = array_values($files);
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
                $out = json_encode($out, \JSON_PRETTY_PRINT | \JSON_FORCE_OBJECT);
            }
            file_put_contents($file, $out);
        }

        Profile::stopTimer(__METHOD__);
    }
}
