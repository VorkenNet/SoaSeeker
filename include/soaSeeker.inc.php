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
  //print_r($virtuals);
  foreach($virtuals as $virtual){
      $result=soaSeekerSingleVirtual($virtual);

      $results=array_merge($results,$result);
  }
  return $results;
}

function zombieSeeker($soArrays){
  $zombies=array();
  foreach($soArrays as $soArray){
    if($soArray["MyIp"]!=$soArray["ip"]){
      //Zombie?
      $zombie["MyIp"]= $soArray["MyIp"];
      $zombie["ip"] = $soArray["ip"];
      $zombie["fqdn"] = $soArray["fqdn"];
      $zombie["soa"] = $soArray["soa"];
      $zombie["DocRoot"] = $soArray["DocRoot"];
      $zombies[]=$zombie;
    }
  }
  return $zombies;
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
  $outputs=array();
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
  $return=array();
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
  //exec("ls ".$directory." | egrep '\.conf$|\.vhost$' 2>&1", $files);

  exec("find ".$directory." -type f | egrep '\.conf$|\.vhost$' 2>&1", $files);
  /*foreach ($files as $file){
    $fullPath[]=$directory."/".$file;
  };
  return $fullPath;*/
  return $files;
}

function getInstanceConf($ip,$user,$password,$rootDir){
  if (!file_exists($rootDir.$ip)) {
      echo "CreateDirectory for $ip\n";
      mkdir($rootDir.$ip, 0777, true);
  }
  getHttpdConFiles($ip,$user,$password,$rootDir);
}

function getHttpdCompileSettings($ip,$user,$password){
  exec("sshpass -p '".$password."' ssh -t ".$user."@".$ip." 'httpd -V' 2>&1", $output);
  $httpdConf=array();
  if (count($output)>2){//controllo grezzo non dia errore
    $SEFound=false;
    foreach ($output as $line){
      if ($line=="Server compiled with...."){
        $SEFound=true;
        continue;
      }
      if(!$SEFound){
        //echo "Prima -".$line."\n";
        $split=explode(":",$line);
        $httpdConf[trim($split[0])]=trim($split[1]);
      } else{
        //echo "Dopo -".$line."\n";
        $split=explode("=",$line);
        if(isset($split[0])){
          $key=trim(str_replace("-D", "", $split[0]));
          if(isset($split[1])) $value=trim($split[1]);
          else $value="";
          $httpdConf[$key]=$value;
        }
      }
    }
  } else die("FAIL: Non riesco a leggere la Configurazione del Httpd! - $ip - $user :: \n");
  return $httpdConf;
}

function getHttpdConFilePath($httpdConf){
   //print_r($httpdConf);
   return trim($httpdConf["HTTPD_ROOT"],'"')."/".trim($httpdConf["SERVER_CONFIG_FILE"],'"');
}

function downloadHttpdConf($httpdConfFilePath,$ip,$user,$password,$rootDir){
    exec($command="sshpass -p '".$password."' scp ".$user."@".$ip.":".$httpdConfFilePath." ".$rootDir.$ip."/httpd.conf 2>&1",$output);
    if(count($output)>0) die ("Fail to Download Httpd Conf".print_r($output));
    else return $rootDir.$ip."/httpd.conf";
}

function getIncludeFileList($httpdConf,$dowloadedHttpdConf){
  //https://httpd.apache.org/docs/2.4/mod/core.html#include
  exec("grep \"^Include\" ".$dowloadedHttpdConf,$output);
  //Remember to add also IncludeOptional
  foreach($output as $include){
    $split=explode(" ",$include);
    if ($split[1][0] != "/") $files[]=trim($httpdConf["HTTPD_ROOT"],'"')."/".$split[1];
    else $files[]=$split[1];
  }
  return $files;
}

function downloadFileConf($ip,$user,$password,$rootDir,$file){
      //https://httpd.apache.org/docs/2.4/mod/core.html#include
      //if Include points to a directory, rather than a file, Apache httpd will read all files in that directory and any subdirectory.
      //However, including entire directories is not recommended, because it is easy to accidentally leave temporary files in a directory that can cause httpd to fail.
      if ($file[strlen($file)-1] != "/"){
        //singleFile or wildCard
        $download="sshpass -p '".$password."' scp ".$user."@".$ip.":".$file." ".$rootDir.$ip."/ 2>&1";
      } else{
        //is a f**king directory
        $download="sshpass -p '".$password."' scp -r ".$user."@".$ip.":".$file." ".$rootDir.$ip." 2>&1";
      }
      exec($download, $output);
}

function getHttpdConFiles($ip,$user,$password,$rootDir){
  //apachectl -V | grep SERVER_CONFIG_FILE
  //-D SERVER_CONFIG_FILE="conf/httpd.conf"
  $httpdConf=getHttpdCompileSettings($ip,$user,$password); //Preleva httpd -V
  $httpdConfFilePath=getHttpdConFilePath($httpdConf); //Estrae il path del file principale di configurazione
  $dowloadedHttpdConf=downloadHttpdConf($httpdConfFilePath,$ip,$user,$password,$rootDir);
  $files=getIncludeFileList($httpdConf,$dowloadedHttpdConf);
  foreach ($files as $file){
      downloadFileConf($ip,$user,$password,$rootDir,$file);
  }
}


 ?>
