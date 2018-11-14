<?php
$table = $_POST['table'];
$text = stripslashes($_POST['text']);
$myfile = fopen("$table.sql", "a") or die("Unable to open file!");
fwrite($myfile, $text);
fclose($myfile);