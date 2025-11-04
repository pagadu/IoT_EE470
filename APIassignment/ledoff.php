<?php
$file = fopen("results.txt","w");
fwrite($file, "off");
fclose($file);
echo "LED turned OFF";
?>
