<?php
require_once("include/soaSeeker.inc.php");
$file=trim($argv[1]);
$hosts=getHostsFromFile($file);
$vhosts=parseHosts($hosts);
displayResult($vhosts);
?>
