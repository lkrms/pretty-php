<?php

class User
{
    public string $username {
        final set => strtolower($value);
    }
}

class Manager extends User
{
    public string $username {
        // This is allowed
        get => strtoupper($this->username);

        // But this is NOT allowed, because set is final in the parent.
        set => strtoupper($value);
    }
}
?>