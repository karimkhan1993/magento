<?php
$arr = array(54,3,23,21,45,66,2,21,232,34,343,34,3,34);
$small = $arr[0];
$large = $arr[0];
foreach($arr as $val)
{
	if($small > $val)
	{
		$small = $val;
	}
	
	if($large < $val)
	{
		$large = $val;
	}
}
echo "Small number = ".$small;
echo "<br>";
echo "Large number = ".$large;
?>