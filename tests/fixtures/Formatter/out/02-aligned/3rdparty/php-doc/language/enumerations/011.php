<?php

enum Size
{
    case Small;
    case Medium;
    case Large;

    public static function fromLength(int $cm): self
    {
        return match (true) {
            $cm < 50  => self::Small,
            $cm < 100 => self::Medium,
            default   => self::Large,
        };
    }
}
?>