<?php
foreach (UserStatus::cases() as $case) {
	printf('<option value="%s">%s</option>\n', $case->value, $case->label());
}
?>