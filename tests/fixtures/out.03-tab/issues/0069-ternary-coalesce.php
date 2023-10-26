<?php
$threshold =
	$this->UncertaintyThreshold === null
		? null
		: (is_array($this->UncertaintyThreshold)
			? $this->UncertaintyThreshold[$algorithm] ?? null
				: $this->UncertaintyThreshold);
