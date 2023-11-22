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
use Lkrms\Facade\Console;
use Lkrms\Facade\Profile;
use Lkrms\Facade\Sys;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
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
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Test;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;
use SebastianBergmann\Diff\Differ;
use RuntimeException;
use SplFileInfo;
use Throwable;
use UnexpectedValueException;

/**
 * Provides pretty-php's command-line interface
 */
class FormatPhp extends CliCommand
{
    private const SKIP_RULE_MAP = [
        'simplify-strings' => NormaliseStrings::class,
        'preserve-newlines' => PreserveLineBreaks::class,
        'declaration-spacing' => DeclarationSpacing::class,
    ];

    private const ADD_RULE_MAP = [
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
        'symmetrical-brackets' => 'SymmetricalBrackets',
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
                'symmetrical-brackets' => false,
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
                'symmetrical-brackets' => false,
                'increase-indent-between-unenclosed-tags' => false,
                'relax-alignment-criteria' => true,
                'preset-rules' => [
                    WordPress::class,
                ],
                'token-type-index' => WordPressTokenTypeIndex::class,
            ],
        ],
    ];

    private const INCOMPATIBLE_RULES = [
        ['align-lists', 'strict-lists'],
    ];

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
     * @var bool
     */
    private $OperatorsFirst;

    /**
     * @var bool
     */
    private $OperatorsLast;

    /**
     * @var bool
     */
    private $IgnoreNewlines;

    /**
     * @var bool
     */
    private $NoSimplifyStrings;

    /**
     * @var string|null
     */
    private $HeredocIndent;

    /**
     * @var string|null
     */
    private $SortImportsBy;

    /**
     * @var bool
     */
    private $NoSortImports;

    /**
     * @var bool
     */
    private $Psr12;

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
     * @var int|null
     */
    private $SpacesBesideCode;

    /**
     * @var bool|null
     */
    private $SymmetricalBrackets;

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
                ->long('src')
                ->valueName('path')
                ->description(<<<EOF
Files and directories to format.

If the only path is a dash ('-'), or no paths are given, `{{command}}` reads
from the standard input and writes to the standard output.

Directories are searched recursively for files to format.
EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()
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
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
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
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
                ->bindTo($this->Spaces),
            CliOption::build()
                ->long('eol')
                ->short('l')
                ->valueName('sequence')
                ->description(<<<'EOF'
Set the output file's end-of-line sequence.

In *platform* mode, `{{command}}` uses CRLF ("\\r\\n") line endings on Windows
and LF ("\\n") on other platforms.

In *auto* mode, the input file's line endings are preserved, and *platform* mode
is used as a fallback if there are no line breaks in the input.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(['auto', 'platform', 'lf', 'crlf'])
                ->defaultValue('auto')
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
                ->bindTo($this->Eol),
            CliOption::build()
                ->long('disable')
                ->short('i')
                ->valueName('rule')
                ->description(<<<EOF
Disable one of the default formatting rules.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::SKIP_RULE_MAP))
                ->unknownValuePolicy(CliOptionValueUnknownPolicy::DISCARD)
                ->multipleAllowed()
                ->bindTo($this->SkipRules),
            CliOption::build()
                ->long('enable')
                ->short('r')
                ->valueName('rule')
                ->description(<<<EOF
Enable an optional formatting rule.
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
Format braces using the One True Brace Style.
EOF)
                ->bindTo($this->OneTrueBraceStyle),
            CliOption::build()
                ->long('operators-first')
                ->short('O')
                ->description(<<<EOF
Place newlines before operators when splitting code over multiple lines.
EOF)
                ->bindTo($this->OperatorsFirst),
            CliOption::build()
                ->long('operators-last')
                ->short('L')
                ->description(<<<EOF
Place newlines after operators when splitting code over multiple lines.
EOF)
                ->bindTo($this->OperatorsLast),
            CliOption::build()
                ->long('ignore-newlines')
                ->short('N')
                ->description(<<<EOF
Ignore the position of newlines in the input.

This option cannot be overridden by configuration file settings (see
`CONFIGURATION` below). Use `--disable=preserve-newlines` for the same
effect without overriding configuration files.
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
                ->bindTo($this->NoSimplifyStrings),
            CliOption::build()
                ->long('heredoc-indent')
                ->short('h')
                ->valueName('type')
                ->description(<<<EOF
Set the indentation level of heredocs and nowdocs.

With *mixed* indentation (the default), line indentation is applied to heredocs
that start on their own line, otherwise hanging indentation is applied.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::HEREDOC_INDENT_MAP))
                ->bindTo($this->HeredocIndent),
            CliOption::build()
                ->long('sort-imports-by')
                ->short('m')
                ->valueName('order')
                ->description(<<<EOF
Set the sort order for consecutive alias/import statements.

Use `--sort-imports-by=none` to group import statements by type without changing
their order.

Unless disabled by `-M/--no-sort-imports`, the default is to sort imports by
*depth*.
EOF)
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys(self::IMPORT_SORT_ORDER_MAP))
                ->bindTo($this->SortImportsBy),
            CliOption::build()
                ->long('no-sort-imports')
                ->short('M')
                ->description(<<<EOF
Don't sort or group consecutive alias/import statements.
EOF)
                ->bindTo($this->NoSortImports),
            CliOption::build()
                ->long('psr12')
                ->description(<<<EOF
Enforce strict PSR-12 / PER Coding Style compliance.

Use this option to apply formatting rules and internal options required for
`{{command}}` output to satisfy the formatting-related requirements of PHP-FIG
coding style standards.
EOF)
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
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
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

Use this option to skip detection of configuration files that would otherwise
take precedence over formatting options given on the command line.

See `CONFIGURATION` below.
EOF)
                ->bindTo($this->IgnoreConfigFiles),
            CliOption::build()
                ->long('output')
                ->short('o')
                ->valueName('file')
                ->description(<<<EOF
Write output to a different file.

If <file> is a dash ('-'), `{{command}}` writes to the standard output.
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
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
                ->bindTo($this->StdinFilename),
            CliOption::build()
                ->long('debug')
                ->valueName('directory')
                ->description(<<<EOF
Create debug output in <directory>.

Combine with `-v/--verbose` to render output to a subdirectory of <directory>
after processing each pass of each rule.
EOF)
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->App->getTempPath() . '/debug')
                ->visibility((Env::debug() ? Visibility::ALL & ~Visibility::SYNOPSIS : Visibility::MARKDOWN | Visibility::MAN_PAGE) | Visibility::HIDE_DEFAULT)
                ->bindTo($this->DebugDirectory),
            CliOption::build()
                ->long('timers')
                ->description(<<<EOF
Report timers and resource usage on exit.
EOF)
                ->visibility(Env::debug() ? Visibility::ALL & ~Visibility::SYNOPSIS : Visibility::MARKDOWN | Visibility::MAN_PAGE)
                ->bindTo($this->ReportTimers),
            CliOption::build()
                ->long('fast')
                ->description(<<<EOF
Skip equivalence checks.
EOF)
                ->visibility(Visibility::ALL & ~Visibility::SYNOPSIS)
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
`{{command}}` looks for a JSON configuration file named *.prettyphp* or
*prettyphp.json* in the same directory as each input file, then in each of its
parent directories. It stops looking when it finds a configuration file, a
*.git* directory, a *.hg* directory, or the root of the filesystem, whichever
comes first.

If an input file has an applicable configuration file, command-line formatting
options other than `-N/--ignore-newlines` are replaced with settings from the
configuration file.

The `--print-config` option can be used to generate a configuration file, for
example:

    $ {{command}} -P -S -M --print-config src tests bootstrap.php
    {
        "src": [
            "src",
            "tests",
            "bootstrap.php"
        ],
        "includeIfPhp": true,
        "noSimplifyStrings": true,
        "noSortImports": true
    }

The optional *src* array specifies files and directories to format. If
`{{command}}` is started with no path arguments in a directory where *src* is
configured, or the directory is passed to `{{command}}` for formatting, paths in
*src* are formatted. It is ignored otherwise.

If a directory contains more than one configuration file, `{{command}}` reports
an error and exits without formatting anything.
EOF,
            CliHelpSectionName::EXIT_STATUS => <<<EOF
`{{command}}` returns 0 when formatting succeeds, 1 when invalid arguments are
given, and 2 when one or more input files cannot be parsed. Other non-zero
values are returned for other failures.

When `--diff` or `--check` are given, `{{command}}` returns 0 when the input is
already formatted and 1 when formatting is required. The meaning of other return
values is unchanged.
EOF,
        ];
    }

    protected function run(...$params)
    {
        if ($this->DebugDirectory !== null) {
            File::createDir($this->DebugDirectory);
            $this->DebugDirectory = realpath($this->DebugDirectory) ?: null;
            if (!Env::debug()) {
                Env::debug(true);
            }
            $this->App->logOutput();
        }

        if ($this->ReportTimers) {
            $this->App->registerShutdownReport(ConsoleLevel::NOTICE);
        }

        if ($this->Tabs && $this->Spaces) {
            throw new CliInvalidArgumentsException('--tab and --space cannot both be given');
        }

        if ($this->SortImportsBy && $this->NoSortImports) {
            throw new CliInvalidArgumentsException('--sort-imports-by and --no-sort-imports cannot both be given');
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
                    if (Test::isAbsolutePath($file)) {
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
        if (!$in && !$this->StdinFilename && !$this->PrintConfig && stream_isatty(STDIN)) {
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
                Console::debug('New formatter required for:', $file);
                $this->applyFormattingOptionValues($options);
                !$this->Verbose || Console::debug('Applying options:', json_encode($options, JSON_PRETTY_PRINT));
                if ($this->Psr12) {
                    $this->Tabs = null;
                    $this->Spaces = 4;
                    $this->Eol = 'lf';
                    $unskip = [
                        DeclarationSpacing::class,
                    ];
                    $skip = [
                        PreserveOneLineStatements::class,
                        AlignLists::class,
                    ];
                    $add = [
                        StrictExpressions::class,
                        StrictLists::class,
                    ];
                    $this->SkipRules = array_diff($this->SkipRules, $unskip, $skip);
                    array_push($this->SkipRules, ...$skip);
                    array_push($this->AddRules, ...array_diff($add, $this->AddRules));
                    $this->OneTrueBraceStyle = false;
                    $this->HeredocIndent = 'hanging';
                }
                $f = new Formatter(
                    !$this->Tabs,
                    $this->Tabs ?: $this->Spaces ?: 4,
                    $this->SkipRules,
                    $this->AddRules,
                    $this->SkipFilters,
                    ($this->Quiet < 2 ? FormatterFlag::REPORT_CODE_PROBLEMS : 0)
                        | ($this->Verbose ? FormatterFlag::LOG_PROGRESS : 0),
                    $this->TokenTypeIndex
                        ? [$this->TokenTypeIndex, 'create']()
                        : ($this->OperatorsFirst
                            ? (new TokenTypeIndex())->withLeadingOperators()
                            : ($this->OperatorsLast
                                ? (new TokenTypeIndex())->withTrailingOperators()
                                : null))
                );
                $f->PreferredEol = $this->Eol === 'auto' || $this->Eol === 'platform'
                    ? PHP_EOL
                    : ($this->Eol === 'lf' ? "\n" : "\r\n");
                $f->PreserveEol = $this->Eol === 'auto';
                $f->OneTrueBraceStyle = $this->OneTrueBraceStyle;
                if ($this->HeredocIndent) {
                    $f->HeredocIndent = self::HEREDOC_INDENT_MAP[$this->HeredocIndent];
                }
                $f->ImportSortOrder = $this->SortImportsBy
                    ? self::IMPORT_SORT_ORDER_MAP[$this->SortImportsBy]
                    : ImportSortOrder::DEPTH;
                $this->SpacesBesideCode === null || $f->SpacesBesideCode = $this->SpacesBesideCode;
                $this->SymmetricalBrackets === null || $f->SymmetricalBrackets = $this->SymmetricalBrackets;
                $this->IncreaseIndentBetweenUnenclosedTags === null || $f->IncreaseIndentBetweenUnenclosedTags = $this->IncreaseIndentBetweenUnenclosedTags;
                $this->RelaxAlignmentCriteria === null || $f->RelaxAlignmentCriteria = $this->RelaxAlignmentCriteria;
                $lastOptions = $options;

                return $this->Psr12
                    ? $f->withPsr12Compliance()
                    : $f;
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
            Profile::startTimer($inputFile, 'file');
            try {
                $output = $formatter->format(
                    $input,
                    $inputFile,
                    $this->Fast
                );
            } catch (InvalidSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(2);
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

        $this->SkipFilters = [];
        if ($this->IgnoreNewlines) {
            $this->SkipRules[] = 'preserve-newlines';
        }
        if ($this->NoSimplifyStrings) {
            $this->SkipRules[] = 'simplify-strings';
        }
        if ($this->NoSortImports && !$this->Psr12) {
            $this->SkipFilters[] = SortImports::class;
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
        if ($this->PresetRules) {
            array_push($this->AddRules, ...$this->PresetRules);
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

        Profile::startTimer(__METHOD__);

        $logDir = "{$this->DebugDirectory}/progress-log";
        File::createDir($logDir);
        File::find()
            ->in($logDir)
            ->doNotRecurse()
            ->forEach(fn(SplFileInfo $file) => unlink((string) $file));

        // Only dump output logged after something changed
        $i = 0;
        $last = null;
        foreach ($log ?: [] as $after => $out) {
            if ($i++ && $out === $last) {
                continue;
            }
            $logFile = sprintf('progress-log/%03d-%s.php', $i, $after);
            $last = $logFiles[$logFile] = $out;
        }

        foreach (array_merge([
            'input.php' => $input,
            'output.php' => $output,
            'tokens.json' => $tokens,
            is_string($data)
                ? 'data.out'
                : 'data.json' => $data,
        ], $logFiles ?? []) as $file => $contents) {
            $file = "{$this->DebugDirectory}/{$file}";
            File::delete($file);
            if ($contents !== null) {
                file_put_contents(
                    $file,
                    is_string($contents)
                        ? $contents
                        : json_encode($contents, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT)
                );
            }
        }

        Profile::stopTimer(__METHOD__);
    }
}
