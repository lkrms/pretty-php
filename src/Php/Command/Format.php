<?php

namespace Lkrms\Pretty\Php\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Pretty\Php\PhpFormatter;
use RuntimeException;

class Format extends CliCommand
{
    public function getDescription(): string
    {
        return "Format a PHP file";
    }

    protected function _getName(): array
    {
        return ["php", "format"];
    }

    protected function _getOptions(): array
    {
        return [[
            "long"        => "file",
            "short"       => "f",
            "valueName"   => "PATH",
            "description" => "PHP file to format",
            "optionType"  => CliOptionType::VALUE,
        ], [
            "long"        => "help",
            "short"       => "h",
            "description" => "Display usage information"
        ]];

    }

    protected function run(...$params)
    {
        $file = $this->getOptionValue("file") ?: "php://stdin";
        $in   = file_get_contents($file);

        $fmt = new PhpFormatter();
        $out = $fmt->format($in);
        echo json_encode($fmt->Tokens);
    }
}

