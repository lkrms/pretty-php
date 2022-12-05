<?php

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\CommaCommaComma;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;

class FormatPhp extends CliCommand
{
    /**
     * @var string|null
     */
    private $DebugDirectory;

    /**
     * @var array<string,string>
     */
    private $SkipMap = [
        "preserve-newlines"      => PreserveNewlines::class,
        "space-around-operators" => SpaceOperators::class,
        "space-after-commas"     => CommaCommaComma::class,
        "indent-heredocs"        => ReindentHeredocs::class,
    ];

    public function getDescription(): string
    {
        return "Format a PHP file";
    }

    protected function getOptionList(): array
    {
        return [
            (CliOption::build()
                ->long("file")
                ->description(<<<EOF
                One or more PHP files to format

                If no files are named on the command line, __{{command}}__
                reads the standard input and writes formatted code to the
                standard output.
                EOF)
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->multipleAllowed()),
            (CliOption::build()
                ->long("tab")
                ->short("t")
                ->description("Indent using tabs")),
            (CliOption::build()
                ->long("space")
                ->short("s")
                ->description("Indent using spaces")
                ->optionType(CliOptionType::ONE_OF_OPTIONAL)
                ->allowedValues(["2", "4"])
                ->defaultValue("4")),
            (CliOption::build()
                ->long("skip")
                ->short("i")
                ->valueName("RULE")
                ->description("Skip one or more rules")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipMap))
                ->multipleAllowed()),
            (CliOption::build()
                ->short("n")
                ->description("Shorthand for '--skip preserve-newlines'")),
            (CliOption::build()
                ->long("stdout")
                ->short("o")
                ->description("Write to the standard output")),
            (CliOption::build()
                ->long("debug")
                ->valueName("DIR")
                ->description("Create debug output in DIR")
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->app()->TempPath . "/pretty-php")),
        ];
    }

    protected function run(...$params)
    {
        $tab   = $this->getOptionValue("tab");
        $space = $this->getOptionValue("space");
        if ($tab && $space) {
            throw new CliArgumentsInvalidException("--tab and --space cannot be used together");
        }
        $tab = $tab ? "\t" : ($space === "2" ? "  " : "    ");

        $skip = $this->getOptionValue("skip");
        if ($this->getOptionValue("n")) {
            $skip[] = "preserve-newlines";
        }
        $skip = array_values(array_intersect_key($this->SkipMap, array_flip($skip)));

        $files  = $this->getOptionValue("file");
        $stdout = $this->getOptionValue("stdout");
        if (!$files && stream_isatty(STDIN)) {
            throw new CliArgumentsInvalidException("FILE required when input is a TTY");
        } elseif (!$files) {
            $files  = ["php://stdin"];
            $stdout = true;
        }
        if ($stdout) {
            Console::registerStderrTarget(true);
        }

        $debug = $this->getOptionValue("debug");
        if (!is_null($debug)) {
            Env::debug(true);
            File::maybeCreateDirectory($debug);
            $debug = $this->DebugDirectory = realpath($debug) ?: null;
        }

        $formatter            = new Formatter($tab, $skip);
        [$i, $count, $errors] = [0, count($files), []];
        foreach ($files as $file) {
            Console::info(sprintf("Formatting %d of %d:", ++$i, $count), $file);
            $input = file_get_contents($file);
            try {
                $output = $formatter->format($input);
            } catch (PrettyBadSyntaxException $ex) {
                Console::exception($ex);
                $this->setExitStatus(2);
                $errors[] = $file;
                continue;
            } catch (PrettyException $ex) {
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getTokens(), $ex->getData());
                throw $ex;
            }
            $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens, null);

            if ($stdout) {
                print $output;
                continue;
            }

            if ($input === $output) {
                Console::log("Nothing to do");
                continue;
            }

            Console::log("Replacing", $file);
            file_put_contents($file, $output);
        }

        if ($errors) {
            Console::error(
                Convert::plural(count($errors), "file", null, true) . " with invalid syntax not formatted:",
                implode("\n", $errors),
                null,
                false
            );
        }

        Console::summary();
    }

    /**
     * @param \Lkrms\Pretty\Php\Token[] $tokens
     * @param mixed $data
     */
    private function maybeDumpDebugOutput(string $input, ?string $output, ?array $tokens, $data): void
    {
        if (!is_null($this->DebugDirectory)) {
            foreach ([
                "input.php"   => $input,
                "output.php"  => $output,
                "tokens.json" => $tokens,
                "data.json"   => $data,
            ] as $file => $contents) {
                $file = "{$this->DebugDirectory}/{$file}";
                File::maybeDelete($file);
                if (!is_null($contents)) {
                    file_put_contents(
                        $file,
                        is_string($contents)
                            ? $contents
                            : json_encode($contents, JSON_PRETTY_PRINT)
                    );
                }
            }
        }
    }
}
