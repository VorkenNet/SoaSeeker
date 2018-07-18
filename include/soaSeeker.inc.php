<?php
function parseHosts($hosts,$progress=false){
  $vhosts=array();
  foreach($hosts as $host){
    $vhosts[]=parseHost(trim($host));
    if ($progress) echo".";
  }
  return $vhosts;
}

function parseHost($host){
  $host=trim($host);
  $vhost["fqdn"]=$host;
  $vhost["domain"]=getMyDomain($host);
  $vhost["ip"]=getIP($host);
  $vhost["soa"]=getSOA($vhost["domain"]);
  $vhost["ttl"]=getTTL($vhost["domain"]);

  return $vhost;
}

function displayResult($vhosts){
  foreach ($vhosts as $vhost){
    $output=implode("\t",$vhost);
    //echo $vhost["fqdn"]."\t".$vhost["domain"]."\t".$vhost["ip"]."\t".$vhost["soa"]."\n";
    //echo "|".$vhost["fqdn"]."|".$vhost["domain"]."|".$vhost["ip"]."|".$vhost["soa"]."|\n";
    //echo $vhost["fqdn"].",".$vhost["domain"].",".$vhost["ip"].",".$vhost["soa"].",".$vhost["ttl"]."\n";
    //$output=implode(",",$vhost);
    echo $output."\n";
  }
}

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

function getHostsFromFile($file){
  $domanins=array();
  $Thisfile = fopen($file, 'r')or die('No open ups..');
  while(!feof($Thisfile)){
    $line = fgets($Thisfile);
    $line = trim($line);
    if (strpos($line, "#") !== 0){
      //se la riga non è commentata
      if (strpos($line, "ServerName") !== false){
        $pieces=explode(" ",$line);
        $domanins[]= $pieces[1]."\n";
      }
      if (strpos($line, "ServerAlias") !== false){
        $pieces=explode(" ",$line);
        unset($pieces[0]);
        foreach ($pieces as $piece) if(strlen(trim($piece))) $domanins[]= $piece."\n";
      }
    }
  }
  return $domanins;
}

?>
