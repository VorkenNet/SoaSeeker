<?php
require_once("include/soaSeeker.inc.php");
$rootDir="Httpd/";
$toParse[]=array("IP","USER","PASSWORD");

#####

#####MAIN#####

foreach($toParse as $istance){
  $ip=$istance[0];
  $user=$istance[1];
  $password=$istance[2];
  //Crea Directory e scarica realitvi file conf
  getInstanceConf($ip,$user,$password,$rootDir);
  //Estrae la lista dei file presenti nella directory di un Ip
  $files=getFileConfList($rootDir.$ip);

  $vhosts=array();
  echo"\n";
  foreach($files as $file){
    print_r(parseConfFile($file));
    //die();
    //genera l'elenenco dei ServerName/ServerAlias
    $hosts=getHostsFromFile($file);
    //genera l'array soaSeeker per il file analizzato
    //--getMyDomain($host)
    //--getIP($host)
    //--getSOA($vhost["domain"])
    //--getTTL($vhost["domain"]);
    $vhost=parseHosts($hosts,true);
    if (isset($vhost)){
      //cerco il DocumentRoot
      $vhost=addDocumentRoot($vhost,$file);
      $vhosts=array_merge($vhosts,$vhost);
    }
  }
  echo"\n";
  $results[$ip]=$vhosts;
}
displayHttpDiggerArray($results);

function displayHttpDiggerArray($results){
  $outputFile="httpDigger.result";
  $cvsFile="httpDigger.cvs";

  file_put_contents($outputFile,"");
  file_put_contents($cvsFile,"");

  foreach($results as $key=>$data){
    foreach ($data as $vhost){
      $myhost["ip"]=$vhost["ip"];
      $myhost["soa"]=$vhost["soa"];
      $myhost["fqdn"]=$vhost["fqdn"];
      $myhost["domain"]=$vhost["domain"];
      $myhost["ttl"]=$vhost["ttl"];
      $myhost["docRoot"]=$vhost["docRoot"];
      $output=implode("\t",$myhost);
      $cvs=implode(",",$myhost);
      $string=$key."\t".$output."\n";
      $cvsString=$key.",".$output."\n";
      echo $string;
      file_put_contents($outputFile,$string, FILE_APPEND);
      file_put_contents($cvsFile,$cvsString, FILE_APPEND);
    }
  }
}

function addDocumentRoot($vhost,$file){
  foreach($vhost as $key=>$host){
    $documentRoot=searchDocumentRoot($host["fqdn"],$file);
    //echo $host["fqdn"]."-".$documentRoot."\n";
    $vhost[$key]["docRoot"]=$documentRoot;
  }
  return $vhost;
}

function getInstanceConf($ip,$user,$password,$rootDir){
  if (!file_exists($rootDir.$ip)) {
      echo "CreateDirectory for $ip\n";
      mkdir($rootDir.$ip, 0777, true);
  }
  getHttpdConFiles($ip,$user,$password,$rootDir);
}

function getFileConfList($directory){
  exec("ls ".$directory." | egrep '\.conf$|\.vhost$' 2>&1", $files);
  foreach ($files as $file){
    $fullPath[]=$directory."/".$file;
  };
  return $fullPath;
}

function parseConfFile($file){
  $lines = file($file);
  $virtuals=array();
  //Remove spaces and Comment
  $lines=cleanConf($lines);
  foreach($lines as $key=>$line){
    if (strpos($line, "<VirtualHost")!==false){
      for ($i=$key; $i<=count($lines); $i++){
          if (trim($lines[$i])=="</VirtualHost>"){
            $keyEnd=$i;
          break;
          }
      }
      $virtual = array_slice($lines, $key, $keyEnd-$key+1);
      $virtuals[]= parseRawVirtual($virtual);
    }
  }

  return $virtuals;
}

function cleanConf($array){
  //https://httpd.apache.org/docs/2.4/configuring.html
  //Lines that begin with the hash character "#" are considered comments, and are ignored.
  //Comments may not be included on the same line as a configuration directive.
  //White space occurring before a directive is ignored, so you may indent directives for clarity.
  //Blank lines are also ignored.
  $clean=array();
  foreach($array as $key=>$element){
      $split=explode("#",$element);
      $element=$split[0];
      if (trim($element)) $clean[]=$element;
  }
  return $clean;
}

function parseRawVirtual($virtual){
/*
            [0] => <VirtualHost *:80>
            [1] =>     ServerAdmin webmaster@nextmove.it
            [2] =>     DocumentRoot /home/sites/nextmove/hbi-group/hbigroup-site/www
            [3] =>     ServerName www.hbigroup.it
            [4] =>     ErrorLog logs/hbigroup-site-error_log
            [5] =>     CustomLog logs/hbigroup-site-access_log combined
            [6] =>     AddDefaultCharset UTF-8
            [7] => </VirtualHost>
*/
  $result=array();
  //Estrai i Valori del Virtual
  $virtual[0]=trim(trim(trim($virtual[0]),"<"),">");
  $split=explode(" ",$virtual[0]);
  $split2=explode(":",$split[1]);
  $tmp["addr"]=$split2[0];
  $tmp["port"]=$split2[1];
  $result[$split[0]]=$tmp;
  unset($virtual[0]);
  unset($virtual[count($virtual)]);
  //estrai i vari field
  foreach ($virtual as $lineconf){
    $lineconf=trim($lineconf);
    $split=explode(" ",$lineconf);
    $key=$split[0];
    unset($split[0]);
    $result[$key]=implode(" ",$split);
  }
  //Estrai i ServerAlias
  if (isset($result["ServerAlias"])){
    $severAlias=explode(" ",$result["ServerAlias"]);
    $result["ServerAlias"]=$severAlias;
  }
  return $result;
}

function serachVirtual($file,$fqdn){
  //cerca ed estrae come array tutto il virtual
  $lines = file($file);
  $lines=cleanConf($lines);
  $outuput=array();
  foreach($lines as $key=>$line){
    if ((strpos($line, $fqdn)!==false) && ((strpos($line,"ServerAlias")!==false)||(strpos($line,"ServerName")!==false))) {
      for ($i=$key; $i>=0; $i--){
        if ((trim($lines[$i])=="<VirtualHost *:80>")|| (trim($lines[$i])=="<VirtualHost *:443>")) {
          $keyStart=$i;
          break;
        }
      }
      for ($i=$key; $i<=count($lines); $i++){
        if (trim($lines[$i])=="</VirtualHost>"){
          $keyEnd=$i;
          break;
        }
      }
      $output = array_slice($lines, $keyStart, $keyEnd-$keyStart+1);
      $output=parseRawVirtual($output);
    }
  }
  return $output;
}

function searchDocumentRoot($fqdn,$file){
  //cerca ed estrae come array tutto il virtual
  $virtual=serachVirtual($file,$fqdn);
  if (count($virtual)){
    return $virtual["DocumentRoot"];
  }
  return false;
}

function getHttpdConFiles($ip,$user,$password,$rootDir){
  //get standard Httpd.conf file
  echo "$ip - Download httpd.conf:\t";
  exec("sshpass -p '".$password."' scp ".$user."@".$ip.":/etc/httpd/conf/httpd.conf ".$rootDir.$ip."/httpd.conf 2>&1", $output);
  if($output) echo "notFound\n";
  else echo"ok\n";
  unset($output);
  //get standard vhosts file
  echo "$ip - Download vhost\t";
  exec("sshpass -p '".$password."' scp ".$user."@".$ip.":/etc/httpd/vhosts/*.conf ".$rootDir.$ip."/ 2>&1", $output);
  if($output) echo "notFound\n";
  else echo"ok\n";
  unset($output);
  //get standard site-enabled per Apache2
  echo "$ip - Download sites-enable\t";
  exec("sshpass -p '".$password."' scp ".$user."@".$ip.":/etc/apache2/sites-enabled/*.conf ".$rootDir.$ip."/ 2>&1", $output);
  if($output) echo "notFound\n";
  else echo"ok\n";
  unset($output);
  //get standard site-enabled per ISPConfig
  echo "$ip - Download (ISPConfig) sites-enable\t";
  exec("sshpass -p '".$password."' scp ".$user."@".$ip.":/etc/httpd/conf/sites-enabled/*.vhost ".$rootDir.$ip."/ 2>&1", $output);
  if($output) echo "notFound\n";
  else echo"ok\n";
  unset($output);

}






?>
