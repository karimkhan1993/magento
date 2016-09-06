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
echo "Only for testing purpose";
echo factorial(6);

?>