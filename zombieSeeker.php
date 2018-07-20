<?php
require_once("include/soaSeeker.inc.php");
/*
- Connect via SSh to a server
- Download all Apache Conf File in a local directory
- Parse all Configuration File and Display MyIp + soaSeekerFields + $documentRoot
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
  //ZombieSeeker
  $results=zombieSeeker($results);
  displayArrayResult($results,$output);
}
