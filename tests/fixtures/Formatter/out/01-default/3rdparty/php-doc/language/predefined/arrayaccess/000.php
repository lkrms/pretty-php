<?php
class Obj implements ArrayAccess
{
    public $container = [
        'one' => 1,
        'two' => 2,
        'three' => 3,
    ];

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}

$obj = new Obj;

var_dump(isset($obj['two']));
var_dump($obj['two']);
unset($obj['two']);
var_dump(isset($obj['two']));
$obj['two'] = 'A value';
var_dump($obj['two']);
$obj[] = 'Append 1';
$obj[] = 'Append 2';
$obj[] = 'Append 3';
print_r($obj);
?>