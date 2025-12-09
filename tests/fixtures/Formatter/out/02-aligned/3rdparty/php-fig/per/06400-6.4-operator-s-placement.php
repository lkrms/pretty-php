<?php

$variable1 = $ternaryOperatorExpr
                 ? 'fizz'
                 : 'buzz';

$variable2 = $possibleNullableExpr
                 ?? 'fallback';

$variable3 = $elvisExpr
                 ?: 'qix';

$variable4 = '<foo>'|> strtoupper(...)|> htmlspecialchars(...);
