<?php
class Person
{
	public string $phone {
		set => $this->sanitizePhone($value);
	}

	private function sanitizePhone(string $value): string
	{
		$value = ltrim($value, '+');
		$value = ltrim($value, '1');

		if (!preg_match('/\d\d\d\-\d\d\d\-\d\d\d\d/', $value)) {
			throw new \InvalidArgumentException();
		}
		return $value;
	}
}
?>