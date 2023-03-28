<?php

class One
{
    /* ... */
}

interface Usable
{
    /* ... */
}

interface Updatable
{
    /* ... */
}

// The keyword order here is important. 'extends' must come first.
class Two extends One implements Usable, Updatable
{
    /* ... */
}
?>