<?php
require_once("include/soaSeeker.inc.php");
/*
Parse all Configuration Files in a Directory (not recursive) and Display soaSeekerFields + $documentRoot
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

$directory=trim($argv[1]);
if(isset($argv[2])) $output=trim($argv[2]);
else $output="tab";

$files=listFilesConf($directory);

foreach($files as $file){
  $results=soaSeekerSinglefile($file);
  $results=displaySoaSeekerResult($results);
  displayArrayResult($results,$output);
}

?>
