<?php
interface I
{
    // An implementing class MUST have a publicly-readable property,
    // but whether or not it's publicly settable is unrestricted.
    public string $readable { get; }

    // An implementing class MUST have a publicly-writeable property,
    // but whether or not it's publicly readable is unrestricted.
    public string $writeable { set; }

    // An implementing class MUST have a property that is both publicly
    // readable and publicly writeable.
    public string $both { get; set; }
}

// This class implements all three properties as traditional, un-hooked
// properties. That's entirely valid.
class C1 implements I
{
    public string $readable;

    public string $writeable;

    public string $both;
}

// This class implements all three properties using just the hooks
// that are requested.  This is also entirely valid.
class C2 implements I
{
    private string $written = '';
    private string $all     = '';

    // Uses only a get hook to create a virtual property.
    // This satisfies the "public get" requirement.
    // It is not writeable, but that is not required by the interface.
    public string $readable {
        get => strtoupper($this->writeable);
    }

    // The interface only requires the property be settable,
    // but also including get operations is entirely valid.
    // This example creates a virtual property, which is fine.
    public string $writeable {
        get => $this->written;
        set {
            $this->written = $value;
        }
    }

    // This property requires both read and write be possible,
    // so we need to either implement both, or allow it to have
    // the default behavior.
    public string $both {
        get => $this->all;
        set {
            $this->all = strtoupper($value);
        }
    }
}
?>