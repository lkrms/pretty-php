<?php

class Example
{
    public string $newName = 'Me' {
        set (string $value) {
            if (strlen($value) < 3) {
                throw new \Exception('Too short');
            }
            $this->newName = ucfirst($value);
        }
    }

    public string $department {
        get {
            return $this->values[__PROPERTY__];
        }
        set {
            $this->values[__PROPERTY__] = $value;
        }
    }

    // or
    public string $department {
        get {
            return $this->values[__PROPERTY__];
        }
        set {
            $this->values[__PROPERTY__] = $value;
        }
    }
}
