# Operators

Compiled from the PHP manual.

## Arithmetic

```php
+$a
-$a
$a + $b
$a - $b
$a * $b
$a / $b
$a % $b
$a ** $b
```

## Assignment

```php
$a += $b
$a -= $b
$a *= $b
$a /= $b
$a %= $b
$a **= $b

$a = $a + $b
$a = $a - $b
$a = $a * $b
$a = $a / $b
$a = $a % $b
$a = $a ** $b

$a &= $b
$a |= $b
$a ^= $b
$a <<= $b
$a >>= $b

$a = $a & $b
$a = $a | $b
$a = $a ^ $b
$a = $a << $b
$a = $a >> $b

$a .= $b
$a ??= $b

$a = $a . $b
$a = $a ?? $b
```

## Bitwise

```php
$a & $b
$a | $b
$a ^ $b
~ $a
$a << $b
$a >> $b
```

## Comparison

```php
$a == $b
$a === $b
$a != $b
$a <> $b
$a !== $b
$a < $b
$a > $b
$a <= $b
$a >= $b
$a <=> $b

$a ? $b : $c
$a ?: $b

$a ?? $b
```

## Error control

```php
$value = @$cache[$key]
```

## Execution

```php
$output = `ls -al`
```

## Incrementing/decrementing

```php
++$a
$a++
--$a
$a--
```

## Logical

```php
$a and $b
$a or $b
$a xor $b
! $a
$a && $b
$a || $b
```

## String

```php
$b = $a . "World!"
```

## Array

```php
$a + $b
$a == $b
$a === $b
$a != $b
$a <> $b
$a !== $b
```

## Type

```php
instanceof
```
