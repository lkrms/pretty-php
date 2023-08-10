<?php
class obj implements ArrayAccess
{
	public function offsetSet($offset, $value): void
	{
		var_dump(__METHOD__);
	}

	public function offsetExists($var): bool
	{
		var_dump(__METHOD__);
		if ($var == 'foobar') {
			return true;
		}
		return false;
	}

	public function offsetUnset($var): void
	{
		var_dump(__METHOD__);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($var)
	{
		var_dump(__METHOD__);
		return 'value';
	}
}

$obj = new obj;

echo "Runs obj::offsetExists()\n";
var_dump(isset($obj['foobar']));

echo "\nRuns obj::offsetExists() and obj::offsetGet()\n";
var_dump(empty($obj['foobar']));

echo "\nRuns obj::offsetExists(), *not* obj:offsetGet() as there is nothing to get\n";
var_dump(empty($obj['foobaz']));
?>