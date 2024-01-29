<?php

enum SortOrder
{
	case Asc;
	case Desc;
}

function query($fields, $filter, SortOrder $order = SortOrder::Asc)
{
	/* ... */
}
?>