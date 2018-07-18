<?php
/*####################
Parse File Functions
######################*/
function parseConfFile($file){
  //Parse a Conf File and split virtual into array
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
      $virtuals[] = parseRawVirtual($virtual);
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
  //Parse a RawArray into a VirtualArray
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

/*####################
soaSeeker Functions
######################*/

function soaSeekerSingleHost($host){
  $host=trim($host);
  $vhost["fqdn"]=$host;
  $vhost["domain"]=getMyDomain($host);
  $vhost["ip"]=getIP($host);
  $vhost["soa"]=getSOA($vhost["domain"]);
  $vhost["ttl"]=getTTL($vhost["domain"]);
  return $vhost;
}

function soaSeekerSingleVirtual($virtual){
  $results=array();
  if(isset($virtual["ServerName"])){
    //echo $virtual["ServerName"]."\n";
    $result=soaSeekerSingleHost(trim($virtual["ServerName"]));
    $result["VHost_conf"]=$virtual;
    $results[]=$result;
  }
  if(isset($virtual["ServerAlias"])){
    foreach ($virtual["ServerAlias"] as $alias){
       //echo $alias."\n";
       $result=soaSeekerSingleHost(trim($alias));
       $result["VHost_conf"]=$virtual;
       $results[]=$result;
    }
  }
  return $results;
}

function soaSeekerSinglefile($file){
  $virtuals=parseConfFile($file);
  $results=array();
  foreach($virtuals as $virtual){
      $result=soaSeekerSingleVirtual($virtual);
      $results=array_merge($results,$result);
  }
  return $results;
}

/*####################
soaSeeker NetUtilities
######################*/

function getMyDomain($host){
  $parts=explode(".",$host);
  $partsCount=count($parts);
  if ($partsCount==2) return $host;
  else{
    unset($parts[0]);
    $domain=implode(".",$parts);
    return $domain;
  }
}

function getIP($host){
  exec("dig @8.8.8.8 +noall +answer ".$host, $output);
  if (count($output)){
    $answer=array_filter(explode("\t",end($output)));
    if (strlen(trim(end($answer)))<17) return trim(end($answer));
    else{
      $output=explode(" ",end($answer));
      return trim(end($output));
    }
  }
  else return "IP_NOFOUND";
}

function getSOA($domain){
  exec("dig soa +noall +answer ".$domain, $output);
  if (count($output)){
      $answer=explode("SOA",$output[0]);
      $soa=explode(" ",trim(end($answer)));
      return trim($soa[0]);
  } else return "SOA_NOFOUND";
}

function getTTL($domain){
  exec("dig soa +noall +answer ".$domain, $output);
  if (count($output)){
      $answer=explode("SOA",$output[0]);
      $soa=explode(" ",trim(end($answer)));
      return trim(end($soa));
  } else return "SOA_NOFOUND";
}

/*####################
display Functions
######################*/

function displaySoaSeekerResult($results){
  foreach ($results as $result){
    $output["fqdn"] = $result["fqdn"];
    $output["domain"] = $result["domain"];
    $output["ip"] = $result["ip"];
    $output["soa"] = $result["soa"];
    $output["ttl"] = $result["ttl"];
    if(isset($result["VHost_conf"]["DocumentRoot"])) $output["DocRoot"]=$result["VHost_conf"]["DocumentRoot"];
    else $output["DocRoot"]="";
    $outputs[]=$output;
  }
  return $outputs;
}

function displayArrayResult($results, $type="tab"){
  foreach ($results as $result){
    switch ($type){
      case "csv":
          $output=implode(",",$result);
          echo $output."\n";
          break;
      case "tab":
          $output=implode("\t",$result);
          echo $output."\n";
          break;
      case "wiki":
          $output=implode("|",$result);
          echo "|".$output."|\n";
          break;
      default:
        $output=implode("\t",$result);
        echo $output."\n";
     }
   }
}

function addServerIp($ip,$results){
  foreach($results as $result){
    $parsed["MyIp"]=$ip;
    $parsed=array_merge($parsed, $result);
    $return[]=$parsed;
  }
  return $return;
}

/*####################
httpDigger Functions
######################*/

function listFilesConf($directory){
  exec("ls ".$directory." | egrep '\.conf$|\.vhost$' 2>&1", $files);
  foreach ($files as $file){
    $fullPath[]=$directory."/".$file;
  };
  return $fullPath;
}

function getInstanceConf($ip,$user,$password,$rootDir){
  if (!file_exists($rootDir.$ip)) {
      echo "CreateDirectory for $ip\n";
      mkdir($rootDir.$ip, 0777, true);
  }
  getHttpdConFiles($ip,$user,$password,$rootDir);
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
