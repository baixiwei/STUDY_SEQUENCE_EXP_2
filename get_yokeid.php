<?php
include('p101_database_connect.php');

$subjid = $_POST['subjid'];

$result = mysql_query('SELECT yokeid FROM allusers WHERE sid='.$subjid);
if($result) 
{ 
	$arr = mysql_fetch_array($result);
	$yokeid = $arr['yokeid'];
	echo $yokeid;
}
else 
{ 
	echo "NULL";
}
// should add error checking here...
?>