<?php
require_once("include/soaSeeker.inc.php");

$ip=trim($argv[1]);
$user=trim($argv[2]);
$password=trim($argv[3]);

if(isset($argv[4])) $output=trim($argv[4]);
else $output="tab";


$workDirectory="httpDigger-tmp/";

getInstanceConf($ip,$user,$password,$workDirectory);

$directory=$workDirectory.$ip;

$files=listFilesConf($directory);
foreach($files as $file){
  $results=soaSeekerSinglefile($file);
  $results=displaySoaSeekerResult($results);
  $results=addServerIp($ip,$results);
  displayArrayResult($results,$output);
}
?>
