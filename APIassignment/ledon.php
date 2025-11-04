<?php
$file = fopen("results.txt","w");
fwrite($file, "on");
fclose($file);
echo "LED turned ON";
?>
