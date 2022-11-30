<?php

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Rule\CommaCommaComma;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
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
                ->description("One or more PHP files to format")
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
                ->description("Skip one or more rules")
                ->optionType(CliOptionType::ONE_OF)
                ->allowedValues(array_keys($this->SkipMap))
                ->multipleAllowed()),
            (CliOption::build()
                ->short("n")
                ->description("Shorthand for '--skip preserve-newlines'")),
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
        if ($tab && $space)
        {
            throw new CliArgumentsInvalidException("--tab and --space cannot be used together");
        }
        $tab = $tab ? "\t" : ($space === "2" ? "  " : "    ");

        $skip = $this->getOptionValue("skip");
        if ($this->getOptionValue("n"))
        {
            $skip[] = "preserve-newlines";
        }
        $skip = array_values(array_intersect_key($this->SkipMap, array_flip($skip)));

        $files = $this->getOptionValue("file");
        if (!$files && stream_isatty(STDIN))
        {
            throw new CliArgumentsInvalidException("FILE required when input is a TTY");
        }
        elseif (!$files || $files === ["-"])
        {
            $files = ["php://stdin"];
        }

        $debug = $this->getOptionValue("debug");
        if (!is_null($debug))
        {
            Env::debug(true);
            File::maybeCreateDirectory($debug);
            $debug = $this->DebugDirectory = realpath($debug) ?: null;
        }

        $formatter = new Formatter($tab, $skip);
        foreach ($files as $file)
        {
            $input = file_get_contents($file);
            try
            {
                $output = $formatter->format($input);
            }
            catch (PrettyException $ex)
            {
                $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getData());
                throw $ex;
            }

            $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens);

            print $output;
        }
    }

    private function maybeDumpDebugOutput(string $input, string $output, array $tokens)
    {
        if (!is_null($this->DebugDirectory))
        {
            file_put_contents($this->DebugDirectory . "/input.php", $input);
            file_put_contents($this->DebugDirectory . "/output.php", $output);
            file_put_contents($this->DebugDirectory . "/tokens.json", json_encode($tokens, JSON_PRETTY_PRINT));
        }
    }
}
