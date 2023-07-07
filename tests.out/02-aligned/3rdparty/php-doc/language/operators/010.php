<?php
/*
 * Here are the examples.
 */

echo "\n--- BIT SHIFT RIGHT ON POSITIVE INTEGERS ---\n";

$val    = 4;
$places = 1;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'copy of sign bit shifted into left side');

$val    = 4;
$places = 2;
$res    = $val >> $places;
p($res, $val, '>>', $places);

$val    = 4;
$places = 3;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'bits shift out right side');

$val    = 4;
$places = 4;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'same result as above; can not shift beyond 0');

echo "\n--- BIT SHIFT RIGHT ON NEGATIVE INTEGERS ---\n";

$val    = -4;
$places = 1;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'copy of sign bit shifted into left side');

$val    = -4;
$places = 2;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'bits shift out right side');

$val    = -4;
$places = 3;
$res    = $val >> $places;
p($res, $val, '>>', $places, 'same result as above; can not shift beyond -1');

echo "\n--- BIT SHIFT LEFT ON POSITIVE INTEGERS ---\n";

$val    = 4;
$places = 1;
$res    = $val << $places;
p($res, $val, '<<', $places, 'zeros fill in right side');

$val    = 4;
$places = (PHP_INT_SIZE * 8) - 4;
$res    = $val << $places;
p($res, $val, '<<', $places);

$val    = 4;
$places = (PHP_INT_SIZE * 8) - 3;
$res    = $val << $places;
p($res, $val, '<<', $places, 'sign bits get shifted out');

$val    = 4;
$places = (PHP_INT_SIZE * 8) - 2;
$res    = $val << $places;
p($res, $val, '<<', $places, 'bits shift out left side');

echo "\n--- BIT SHIFT LEFT ON NEGATIVE INTEGERS ---\n";

$val    = -4;
$places = 1;
$res    = $val << $places;
p($res, $val, '<<', $places, 'zeros fill in right side');

$val    = -4;
$places = (PHP_INT_SIZE * 8) - 3;
$res    = $val << $places;
p($res, $val, '<<', $places);

$val    = -4;
$places = (PHP_INT_SIZE * 8) - 2;
$res    = $val << $places;
p($res, $val, '<<', $places, 'bits shift out left side, including sign bit');

/*
 * Ignore this bottom section,
 * it is just formatting to make output clearer.
 */

function p($res, $val, $op, $places, $note = '')
{
    $format = '%0' . (PHP_INT_SIZE * 8) . "b\n";

    printf("Expression: %d = %d %s %d\n", $res, $val, $op, $places);

    echo " Decimal:\n";
    printf("  val=%d\n", $val);
    printf("  res=%d\n", $res);

    echo " Binary:\n";
    printf('  val=' . $format, $val);
    printf('  res=' . $format, $res);

    if ($note) {
        echo " NOTE: $note\n";
    }

    echo "\n";
}
?>