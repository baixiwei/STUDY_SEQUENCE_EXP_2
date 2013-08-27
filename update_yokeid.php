<?php
include('p101_database_connect.php');

$subjid = $_POST['subjid'];
$yokeid = $_POST['yokeid'];

$result = mysql_query('UPDATE allusers SET yokeid='.$yokeid.' WHERE sid='.$subjid);
if($result) { return true; } else { return false; }
// should add error checking here...
?>