<?php


$msg = "Vocês poderiam ser mais educados e cuidadosos";
$cmd = "python3 TextInput.py".$msg;

$mystring = shell_exec("dir");

echo "<br>my:";

echo $mystring;
?>

