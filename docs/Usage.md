## NAME

pretty-php - Format a PHP file

## SYNOPSIS

**`pretty-php`** \[*<u>options</u>*] \[**`--`**] \[*<u>path</u>*...]

## OPTIONS

- *<u>path</u>*...

  Files and directories to format.

  If the only path is a dash ('-'), or no paths are given, **`pretty-php`**
  reads from the standard input and writes to the standard output.

  Directories are searched recursively for files to format.

- **`-I`**, **`--include`** *<u>regex</u>*

  A regular expression for pathnames to include when searching a directory.

  Exclusions (**`-X/--exclude`**) are applied first.

  The default regex is: `/\.php$/`

- **`-X`**, **`--exclude`** *<u>regex</u>*

  A regular expression for pathnames to exclude when searching a directory.

  Exclusions are applied before inclusions (**`-I/--include`**).

  The default regex is: `/\/(\.git|\.hg|\.svn|_?build|dist|vendor)\/$/`

- **`-P`**, **`--include-if-php`**\[=*<u>regex</u>*]

  A regular expression for pathnames to check for PHP code when searching a
  directory.

  Use this option to format files not matched by **`-I/--include`** if they have
  a pathname that matches *<u>regex</u>* and a PHP open tag ('\<?php') at the
  start of the first line that is not a shebang ('#!').

  The default regular expression matches files with no extension. Use
  **`--include-if-php=/./`** to check the first line of all files.

  Exclusions (**`-X/--exclude`**) are applied first.

  The default regex is: `/(\/|^)[^.]+$/`

- **`-t`**, **`--tab`**\[=`2`|`4`|`8`]

  Indent using tabs.

  The `align-chains`, `align-fn`, `align-lists`, and `align-ternary` rules
  cannot be enabled when using tabs for indentation.

  The default size is: `4`

- **`-s`**, **`--space`**\[=`2`|`4`|`8`]

  Indent using spaces.

  This is the default if neither **`-t/--tab`** or **`-s/--space`** are given.

  The default size is: `4`

- **`-l`**, **`--eol`** (`auto`|`platform`|`lf`|`crlf`)

  Set the output file's end-of-line sequence.

  In `platform` mode, **`pretty-php`** uses CRLF ("\\r\\n") line endings on
  Windows and LF ("\\n") on other platforms.

  In `auto` mode, the input file's line endings are preserved, and `platform`
  mode is used as a fallback if there are no line breaks in the input.

  The default sequence is: `auto`

- **`-i`**, **`--disable`** *<u>rule</u>*,...

  Disable one of the default formatting rules.

  The rule can be:

  - `sort-imports`
  - `move-comments`
  - `simplify-strings`
  - `simplify-numbers`
  - `preserve-newlines`
  - `declaration-spacing`

- **`-r`**, **`--enable`** *<u>rule</u>*,...

  Enable an optional formatting rule.

  The rule can be:

  - `align-comments`
  - `align-chains`
  - `align-fn`
  - `align-ternary`
  - `align-data`
  - `align-lists`
  - `blank-before-return`
  - `strict-expressions`
  - `strict-lists`
  - `preserve-one-line`

- **`-1`**, **`--one-true-brace-style`**

  Format braces using the One True Brace Style.

- **`-O`**, **`--operators-first`**

  Place newlines before operators when splitting code over multiple lines.

- **`-L`**, **`--operators-last`**

  Place newlines after operators when splitting code over multiple lines.

- **`-T`**, **`--tight`**

  Remove blank lines between declarations of the same type where possible.

  This option is not ignored when a configuration file is applied.

- **`-N`**, **`--ignore-newlines`**

  Ignore the position of newlines in the input.

  Unlike **`--disable=preserve-newlines`**, this option is not ignored when a
  configuration file is applied.

- **`-S`**, **`--no-simplify-strings`**

  Don't normalise escape sequences in strings, and don't replace single- and
  double-quoted strings with the most readable and economical syntax.

  Equivalent to **`--disable=simplify-strings`**

- **`-n`**, **`--no-simplify-numbers`**

  Don't normalise integers and floats.

  Equivalent to **`--disable=simplify-numbers`**

- **`-h`**, **`--heredoc-indent`** (`none`|`line`|`mixed`|`hanging`)

  Set the indentation level of heredocs and nowdocs.

  If **`--heredoc-indent=mixed`** is given, line indentation is applied to
  heredocs that start on their own line, otherwise hanging indentation is
  applied.

  The default level is: `mixed`

- **`-m`**, **`--sort-imports-by`** (`none`|`name`|`depth`)

  Set the sort order for consecutive alias/import statements.

  Use **`--sort-imports-by=none`** to group import statements by type without
  changing their order.

  The default order is: `depth`

- **`-M`**, **`--no-sort-imports`**

  Don't sort or group consecutive alias/import statements.

  Equivalent to **`--disable=sort-imports`**

- **`-b`**, **`--indent-between-tags`**

  Add a level of indentation to code between indented tags.

- **`--psr12`**

  Enforce strict PSR-12 / PER Coding Style compliance.

- **`-p`**, **`--preset`** (`drupal`|`laravel`|`symfony`|`wordpress`)

  Apply a formatting preset.

  Formatting options other than **`-T/--tight`**, **`-N/--ignore-newlines`** and
  **`--psr12`** are ignored when a preset is applied.

- **`-c`**, **`--config`** *<u>file</u>*

  Read formatting options from a JSON configuration file.

  Settings in *<u>file</u>* override command-line formatting options other than
  **`-T/--tight`** and **`-N/--ignore-newlines`**, and any configuration files
  that would usually apply to the input are ignored.

  See **`CONFIGURATION`** below.

- **`--no-config`**

  Ignore configuration files.

  Use this option to ignore any configuration files that would usually apply to
  the input.

  See **`CONFIGURATION`** below.

- **`-o`**, **`--output`** *<u>file</u>*,...

  Write output to a different file.

  If *<u>file</u>* is a dash ('-'), **`pretty-php`** writes to the standard
  output. Otherwise, **`-o/--output`** must be given once per input file, or not
  at all.

- **`--diff`**\[=`unified`|`name-only`]

  Fail with a diff when the input is not already formatted.

  The default type is: `unified`

- **`--check`**

  Fail silently when the input is not already formatted.

- **`--print-config`**

  Print a configuration file instead of formatting the input.

  See **`CONFIGURATION`** below.

- **`-F`**, **`--stdin-filename`** *<u>path</u>*

  The pathname of the file passed to the standard input.

  Allows discovery of configuration files and improves reporting. Useful for
  editor integrations.

- **`--debug`**\[=*<u>directory</u>*]

  Create debug output in *<u>directory</u>*.

  Combine with **`--log-progress`** to write partially formatted code to a
  series of files in `<directory>/progress-log` that represent changes applied
  by each enabled rule.

- **`--log-progress`**

  Write partially formatted code to files in the debug output directory.

  This option has no effect if **`--debug`** is not given.

- **`--timers`**

  Report timers and resource usage on exit.

- **`--fast`**

  Skip equivalence checks.

- **`-v`**, **`--verbose`**

  Report unchanged files.

- **`-q`**, **`--quiet`**

  Do not report files that require formatting.

  May be given multiple times for less verbose output:

  - **`-qq`**: do not print version information or provide a summary of files
  formatted and replaced on exit.
  - **`-qqq`**: suppress warnings.
  - **`-qqqq`**: suppress TTY-only progress updates.

  Errors are always reported.

## CONFIGURATION

**`pretty-php`** looks for a JSON configuration file named `.prettyphp` or
`prettyphp.json` in the same directory as each input file, then in each of its
parent directories. It stops looking when it finds a configuration file, a
`.git`, `.hg` or `.svn` directory, or the root of the filesystem, whichever
comes first.

If a configuration file is found, **`pretty-php`** formats the input using
formatting options read from the configuration file, and command-line formatting
options other than **`-T/--tight`** and **`-N/--ignore-newlines`** are ignored.

The **`--print-config`** option can be used to generate a configuration file,
for example:

```console
$ pretty-php --sort-imports-by=name --psr12 src tests --print-config
{
    "src": [
        "src",
        "tests"
    ],
    "sortImportsBy": "name",
    "psr12": true
}
```

The optional `src` array specifies files and directories to format when
**`pretty-php`** is started in the same directory or when the directory is
passed to **`pretty-php`** for formatting.

If a directory contains more than one configuration file, **`pretty-php`**
reports an error and exits without formatting anything.

## EXIT STATUS

- `0` when formatting succeeds or the input is already formatted
- `1` when invalid arguments are given
- `2` when invalid configuration files are found
- `4` when one or more input files cannot be parsed
- `8` when formatting is required and **`--diff`** or **`--check`** are given
