<?php

function allowed()
{
	$allowedHeredoc = <<<COMPLIANT
		This
		is
		a
		compliant
		heredoc
		COMPLIANT;

	$allowedNowdoc = <<<'COMPLIANT'
		This
		is
		a
		compliant
		nowdoc
		COMPLIANT;

	var_dump(
		'foo',
		<<<'COMPLIANT'
		This
		is
		a
		compliant
		parameter
		COMPLIANT,
		'bar',
	);
}
