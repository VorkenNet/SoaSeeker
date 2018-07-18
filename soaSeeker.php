<?php
require_once("include/soaSeeker.inc.php");
/*
Parse a single Configuration File and Display soaSeekerFields + $documentRoot
soaSeekerFields:
 - fqdn
 - domain
 - ip
 - soa
 - ttl

 Display tipes:
 - csv
 - tab
 - wiki
*/

$file=trim($argv[1]);
if(isset($argv[2])) $output=trim($argv[2]);
else $output="tab";

$results=soaSeekerSinglefile($file);
$results=displaySoaSeekerResult($results);
displayArrayResult($results,$output);
?>
