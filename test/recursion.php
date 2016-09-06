<?php
function factorial($num)
{
	if($num < 2)
	{
		return 1;
	} else {
	
		return ($num * factorial($num-1));
	}
}
echo "Yes";
echo factorial(6);

?>