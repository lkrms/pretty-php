<?php
enum UserStatus: string
{
    case Pending        = 'P';
    case Active         = 'A';
    case Suspended      = 'S';
    case CanceledByUser = 'C';

    public function label(): string
    {
        return match ($this) {
            static::Pending        => 'Pending',
            static::Active         => 'Active',
            static::Suspended      => 'Suspended',
            static::CanceledByUser => 'Canceled by user',
        };
    }
}
?>