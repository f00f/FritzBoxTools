#!/usr/bin/php
<?php $ver = "fb_tools 0.12 (c) 16.06.2014 by Michael Engelke <http://www.mengelke.de>";

if($var = './fb_config.php' and file_exists($var)) // Config-Datei vorhanden?
 include $var;				// Config-Datei laden
if(!isset($cfg))			// $cfg schon gesetzt?
 $cfg = array(
	'host'	=> 'fritz.box',		// Fritz!Box-Addresse
	'pass'	=> 'password',		// Fritz!Box Kennwort
	'user'	=> false,		// Fritz!Box Username (Optional)
	'port'	=> 80,			// Fritz!Box HTTP-Port (Normalerweise immer 80)
	'fiwa'	=> 100,			// Fritz!Box Firmware (Nur Intern)
	'upnp'	=> 49000,		// Fritz!Box UPnP-Port (Normalerweise immer 49000)
	'sbuf'	=> 4096,		// TCP/IP Socket-Buffergröße
	'tout'	=> 3,			// TCP/IP Socket-Timeout
	'char'	=> 'auto',		// Zeichenkodierung der Console (auto/ansi/oem/utf8)
//	'help'	=> false,		// Hilfe ausgeben
//	'dbug'	=> false,		// Debuginfos ausgeben
	'error' => array(),		// Fehlerlogs
	'preset'=> ((isset($preset)) ? $preset : array(
		'std' => array(		'host' => '192.168.178.1'),	# Standart-IP
		'notfall' => array(	'host' => '169.254.1.1'),	# ZeroConf-IP
	)));

function out($str) {
 if(($var = $GLOBALS['cfg']['char']) == 'oem')
  $str = strtr($str,array('ä' => chr(132), 'ö' => chr(148), 'ü' => chr(129), 'Ä' => chr(142), 'Ö' => chr(153), 'Ü' => chr(154), 'ß' => chr(225), "\n" => "\r\n"));
 elseif($var == 'utf8')
  $str = utf8_encode($str);
 return $str;
}
function request($method,$page,$body=false,$head=array(),$port=false,$host=false) {
 global $cfg;
 foreach(array('host','port') as $var)		// Host & Port setzen
  if(!$$var)
   $$var = $cfg[$var];

 if($mode = preg_match('/^(\w+)(?:-(\w+))?/',$method,$var)) {
  $method = strtoupper($var[1]);
  $mode = (isset($var[2])) ? $var[2] : (($var[1] == strtolower($var[1])) ? 'array' : false);	// Result-Modus festlegen
 }

 if(@$cfg['dbug'] > 2)
  echo out("$host:$port ");

 if($fp = @fsockopen($host,$port,$errnr,$errstr,$cfg['tout'])) {	// Verbindung aufbauen
//  if(is_array($body)) {				// Request-Daten vorbereiten
//   foreach($body as $key => $var)
//    $body[$key] = urlencode($key)."=".urlencode($var);
//   $body = implode('&',$body);
//  }
  if($method == 'POST') {				// POST-Request vorbereiten
   if(is_array($body)) {				// Multipart-Post vorbereiten
    $row = "---".md5(rand().time());
    foreach($body as $key => $var) {
     $val = array('','');
     if(is_array($var))					// Unter-Header im Header
      foreach($var as $k => $v)
       if($k == '')					// Content
        $var = $v;
       elseif($k == 'filename')			// Weitere Angaben im Header
        $val[0] .= "; $k=\"$v\"";
       else						// Sub-Header
        $val[1] = "$k: $v\r\n";
     $body[$key] = "$row\r\nContent-Disposition: form-data; name=\"$key\"$val[0]\r\n$val[1]\r\n$var\r\n";
    }
    $body = implode('',$body)."$row--\r\n";
    $var = "multipart/form-data; boundary=$row";
   }
   else
    $var = 'application/x-www-form-urlencoded';		// Standard Post
   if(!isset($head['content-type']))
    $head['content-type'] = $var;
   if(!isset($head['content-length']))
    $head['content-length'] = strlen($body);
   $body = "\r\n$body";
  }
  elseif($method == 'GET' and $body) {			// GET-Request vorbereiten
   $page .= "?$body";
   $body = "\r\n";
  }
  else
   $body = "\r\n";
  if(!isset($head['host']))				// Host zum Header hinzufügen
   $head['host'] = $host;
  if(!isset($head['connection']))			// Connection zum Header hinzufügen
   $head['connection'] = "Closed";
  foreach($head as $key => $var)			// Header vorbeireiten
   $head[$key] = preg_replace_callback('/(?<=^|\W)\w/','prcb_stu',strtolower($key)).": $var";
  $head = "HTTP/1.1\r\n".implode("\r\n",$head)."\r\n";
  if(@$cfg['dbug'] > 2)
   echo out("$method $page ".((@$cfg['dbug'] > 3) ? $head : "")."$body\n\n");
  fputs($fp,"$method $page $head$body");		// Request Absenden
  $rp = "";						// Antwort vorbereiten
  while(!feof($fp))
   $rp .= fread($fp,$cfg['sbuf']);
  fclose($fp);
  $fp = $rp;
  if($mode != 'raw' and preg_match('/^(http[^\r\n]+)(.*?)\r\n\r\n(.*)$/is',$rp,$array)) {	// Header vom Body trennen
   if($mode == 'array') {
    $fp = array($array[1]);
    if(count($array) > 0 and preg_match_all('/^([^\s:]+):\s*(.*)\s*$/m',$array[2],$array[0]))
     foreach($array[0][2] as $key => $var)
      $fp[preg_replace_callback('/(?<=^|\W)\w/','prcb_stu',$array[0][1][$key])] = $var;
    $fp[1] = $array[3];
   }
   else
    $fp = $array[3];
  }
 }
 else
  $GLOBALS['cfg']['error'][] = array("$host:$port",$errno,$errstr);
 if(@$cfg['dbug'] > 4)
  echo out(print_r($rp,true)."\n");
 return $fp;
}
function prcb_stu($x) {				// PREG_Replace_Callback Uppercase
 return strtoupper($x[0]);
}
function getsockerror() {
 return ($var = end($GLOBALS['cfg']['error'])) ? "Fehler: $var[0] nicht gefunden!\n\n$var[2]\n" : false;
}
function response($xml,$pass) {			// Response berechnen
 return (preg_match('!<Challenge>(\w+)</Challenge>!',$xml,$var)) ? "response=$var[1]-".md5(preg_replace('!.!',"\$0\x00","$var[1]-$pass")) : false;
}
function login($pass=false,$user=false) {	// In der Fritz!Box einloggen
 global $cfg;
 foreach(array('user','pass') as $var)		// User & Pass setzen
  if(!$$var)
   $$var = $GLOBALS['cfg'][$var];
 $sid = false;
 $page = "/login_sid.lua";
 if($rp = request('GET',$page)) {			// Login lua ab 05.29
  if(($auth = response($rp,$pass)) and preg_match('/<SID>(?!0{16})(\w+)<\/SID>/',$rp = request('POST',$page,(($user) ? "$auth&username=$user" : $auth)),$var)) {
   $sid = $var[1];
   $cfg['fiwa'] = 529;
  }
  else {						// Login cgi ab 04.74
   $page = "/cgi-bin/webcm";
   $data = "getpage=../html/login_sid.xml";
   if(!$rp or !$auth = response(($rp),$pass))
    $auth = response(request('GET',"$page?$data"),$pass);
    if($auth and preg_match('/<SID>(\w+)<\/SID>/',request('POST',$page,"$data&login:command/$auth"),$var)) {
    $sid = (preg_match('/0{16}/',$var[1])) ? false : $var[1];
    $cfg['fiwa'] = 474;
   }
   else {						// Login classic
    if(!preg_match('/Anmeldung/',request('POST',$page,"login:command/password=$pass")))
     $sid = true;
   }
  }
 }
 return $cfg['sid'] = $sid;				// SID zurückgeben
}
function logout($sid) {
 if(is_string($sid) and $sid)			// Ausloggen
  request('GET',(($GLOBALS['cfg']['fiwa'] < 529) ? "/cgi-bin/webcm" : "/login_sid.lua"),"security:command/logout=1&logout=1&sid=$sid");
}
function supportcode($str = false) {
 if(!$str and !$str = request('GET','/cgi-bin/system_status'))
  return getsockerror();
 return (preg_match('!
	([^<>-]+)-
	(\w+)-
	([01]\d|2[0-3])([0-2]\d|3[01])(0\d|1[01])-
	([0-2]\d|3[01])([0-5]\d|6[0-3])([0-2]\d|3[01])-
	([0-7]{6})-
	([0-7]{6})-
	(1[49]|21|78|8[35])(67|79)(\d\d)-(\d{2,3})(\d\d)(\d\d)-
	(\d+)-
	(\w+)(?:-(\w+))?
	!x',$str,$array))
  ?	"\nModell: $array[1]\n"
	."Firmware: $array[14].$array[15].$array[16]\n"
	."Version: $array[17]\n"
	.((@$array[19]) ? "Sprache: ".strtr($array[19],array('de' => 'Deutsch', 'en' => 'Englisch'))."\n" : '')
	."Branding: $array[18]\n"
	."Annex: $array[2]\n\n"
	."Laufzeit:".preg_replace(array('/(?<= )0+ \w+(, )?|(?<= )0+(?=\d)/','/, $/','/( 1 \w+)\w(?=,|\s)/'),array('','','$1')," $array[6] Jahre, $array[5] Monate, $array[4] Tage, $array[3] Stunden\n")
	."Neustarts: " .($array[7] * 32 + $array[8])."\n\n"
	."debug.cfg: ".(($array[11]%64 == 14) ? "Nicht v" : "V")."orhanden"./*(($array[11]%64 == 19) ? "" : "!!!").*/"\n"
	."fw_attrib: " .(($array[11] < 64) ? "Modifiziert" : "Unverändert")."\n\n"
	."OEM: ".(($array[12] == 67) ? "Custom" : "Original")."\n"
	."RunClock: $array[13]\n"
  :	"Unbekannt: $str";
}
function upnprequest($page,$ns,$rq,$exp=false) {
 $rp = request('POST',$page,utf8_encode("<"."?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
	."<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" s:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">\n"
	."<s:Body><u:$rq xmlns:u=$ns /></s:Body>\n"
	."</s:Envelope>"),array('content-type' => 'text/xml; charset="utf-8"','soapaction' =>  "\"$ns#$rq\""),$GLOBALS['cfg']['upnp']);
 return ($exp) ? ((preg_match("/<$exp>(.*?)<\/$exp>/",$rp,$var)) ? $var[1] : false) : $rp;
}
function getupnppath($urn) {
 return ($rp = request('GET','/igddesc.xml',false,array(),$GLOBALS['cfg']['upnp']) and preg_match("!<(service)>.*?<(serviceType)>(urn:[^<>]*".$urn."[^<>]*)</\\2>.*?<(controlURL)>(/[^<>]+)</\\4>.*?</\\1>!s",$rp,$var)) ? array($var[3],$var[5]) : false;
}
function getexternalip() {
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'GetExternalIPAddress','NewExternalIPAddress')) ? $var : false;
}
function forcetermination() {
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'ForceTermination','NewExternalIPAddress')) ? $var : false;
}
function savedata($file,$data) {
 if($fp = fopen($file,'w')) {
  fputs($fp,$data);
  fclose($fp);
  $fp = true;
 }
 return $fp;
}
function saverpdata($file,$data,$name) {
 $file = preg_replace('/[<>\[\]:\/\\\\"*?|]/','_',($file) ? $file : ((@preg_match('/filename="(.*)"/',$data['Content-Disposition'],$var)) ? $var[1] : $name));
 $cfg['file'] = $file;
 return savedata($file,$data[1]);
}
function supportdaten($file,$sid=false) {
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $array = array();
 if($sid and $sid !== true)
  $array['sid'] = $sid;
 $array['SupportData'] = '';
 if($data = request('POST-array','/cgi-bin/firmwarecfg',$array))
  return (saverpdata($file,$data,"supportdaten.txt")) ? true : "$cfg[file] kann nicht geschrieben werden!";
 else
  return "Keine Daten erhalten!";
}
function dial($dial,$port=false,$sid=false) {	// Wahlhilfe
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $dial = urlencode(preg_replace('/[^\d*#]/','',$dial));
 $port = ($port) ? preg_replace('/\D+/','',$port) : false;
 if($GLOBALS['cfg']['fiwa'] == 529) {
  if($port)
   request('POST',"/fon_num/dial_fonbook.lua","clicktodial=on&port=$port&btn_apply=&sid=$sid");
  request('GET',"/fon_num/fonbook_list.lua",(($dial == '') ? "hangup=&orig_port=$port" : "dial=$dial")."&sid=$sid");
 }
 else
  request('POST',"/cgi-bin/webcm","telcfg:settings/UseClickToDial=1"
	.(($dial == '') ? "&telcfg:command/Hangup=" : "&telcfg:command/Dial=$dial")
	.(($port) ? "&telcfg:settings/DialPort=$port" : "")."&sid=$sid");
}
function cfgexport($file,$pass=false,$mode=false,$sid=false) {
 $body = array('ImportExportPassword' => $pass, 'ConfigExport' => false);
 if(!$sid and $GLOBALS['cfg']['sid'] !== true)
  $body = array_merge(array('sid' => $GLOBALS['cfg']['sid']),$body);
 return ($data = request('POST-array','/cgi-bin/firmwarecfg',$body))
	? (($mode)
		? cfginfo($data[1],$mode)
		: ((saverpdata($file,$data,"fritzbox.export"))
			? true
			: "$cfg[file] kann nicht geschrieben werden!"))
	: "Keine Daten erhalten!";
}
function cfgcalcsum($data) {
 if(preg_match_all('/^(\w+)=(\S+)\s*$|^(\*{4}) (?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*(.*?)\3 END OF FILE \3\s*$/sm',$data,$array)) {
  foreach($array[4] as $key => $var) {
   if($array[1][$key])
    $array[0][$key] = $array[1][$key].$array[2][$key]."\0";
   elseif($var == 'CFG')
    $array[0][$key] = $array[5][$key]."\0".stripcslashes(strtr(substr($array[6][$key],0,-1),array("\r" => "")));
   elseif($var == 'BIN')
    $array[0][$key] = $array[5][$key]."\0".pack('H*',preg_replace('/\W+/',"",$array[6][$key]));
  }
  $var = sprintf('%X',crc32(join('',$array[0])));
  $key = '/(?<=^\*{4} END OF EXPORT )[A-Z\d]{8}(?= \*{4}\s*$)/m';
 }
 return ($array and preg_match($key,$data,$array)) ? array($array[0],$var,preg_replace($key,$var,$data)) : false;
}
function cfgimport($file,$pass=false,$mode=false,$sid=false) {
 if($data = file_get_contents($file)) {
  if($mode and $var = cfgcalcsum($data))
   $data = $var[2];   
  $body = array('ImportExportPassword' => $pass,
	'ConfigImportFile' => array('filename' => $file, 'Content-Type' => 'application/octet-stream', '' => $data),
	'apply' => false);
  if(!$sid and $GLOBALS['cfg']['sid'] !== true)
   $body = array_merge(array('sid' => $GLOBALS['cfg']['sid']),$body);
  return request('POST-array','/cgi-bin/firmwarecfg',$body);
 }
 else
  return $data;
}
function cfginfo($data,$mode) {
 if(preg_match_all('/^(?:
	\*{4}\s(.*?)\sCONFIGURATION\sEXPORT		# Fritzbox-Modell
	|(\w+=\S+)					# Variablen
	)\s*$|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*(.*?)\s*\*{4}\sEND\sOF\sFILE\s\*{4}\s*$/msx',$data,$array) and $array[1][0] and $data = cfgcalcsum($data)) {
  $list = $val = $vars = array();
  $mstr = $mlen = array(0,0);

// print_r($array);

  foreach($array[3] as $key => $var)
   if($var) {
    $bin = ($array[3][$key] == 'CFG') ? strtr($array[5][$key],array("\r" => "", "\\\\" => "\\")) : pack('H*',preg_replace('/\W+/',"",$array[5][$key]));
    $list[] = array($array[3][$key],$array[4][$key],number_format(strlen($bin),0,",","."));
    if($mode)
     savedata($array[4][$key],$bin);
    unset($array[2][$key]);
   }
   elseif($array[2][$key] and preg_match('/^(\w+)=(.*)$/',$array[2][$key],$var))
    $vars[$var[1]] = $var[2];
   else
    unset($array[2][$key]);
  if(count($vars)) {
   $bin = implode("\n",$array[2]);
   $file = "vers.txt";
   $list[] = array("VAR",$file,number_format(strlen($bin),0,",","."));
   if($mode)
    savedata($file,$bin);
  }
  foreach($list as $key => $var) {
   $c = ($key < count($list)/2) ? 0 : 1;
   $mstr[$c] = max($mstr[$c],strlen($var[1]));
   $mlen[$c] = max($mlen[$c],strlen($var[2]));
  }
  for($a=0;$a<count($list);$a+=2)    
   for($b=0;$b<=1;$b++) {
    $c = ($a/2)+floor(1+count($list)/2)*$b;
    if(isset($list[$c]) and $var=$list[$c])
     $val[$a] .= $var[0].": ".str_pad($var[1],$mstr[$b]," ")." ".str_pad($var[2],$mlen[$b]," ",STR_PAD_LEFT)." Bytes   ";
   }
  $list = "\nModell:   {$array[1][0]}\n";
  if(isset($vars['FirmwareVersion']))
   $list .= "Firmware: $vars[FirmwareVersion]\n";
  return $list."Checksum: $data[0] (".(($data[0] == $data[1]) ? "OK" : "Inkorrekt! - Korrekt: $data[1]").")\n\n"
	.implode("\n",$val)."\n";
 }
 else
  return false;
}

# Eigentlicher Programmstart

if(isset($_SERVER['argv']) and count($_SERVER['argv']) > 0) {	## CLI-Modus ##
 if(@ini_get('pcre.backtrack_limit') < 1000000) 	// Bug ab PHP 5 beheben (Für Große RegEx-Ergebnisse)
  @ini_set('pcre.backtrack_limit',1000000);
 if(!$script = realpath($_SERVER['argv'][0]))
  $script = realpath($_SERVER['argv'][0].".bat");
 $self = basename($script);
 $pmax = count($_SERVER['argv']);
 $pset = 1;	// Optionszähler
 if($pset+1 < $pmax and @preg_match('/^
	(?:([^:]+):)?
	(?:([^@]+)@)?
	([\w.-]+\.[\w.-]+|\[[a-f\d:]+\]|'.strtr(preg_quote(implode("\t",array_keys($cfg['preset']))),"\t",'|').')
	(?::(\d{1,5}))?
		$/ix',$_SERVER['argv'][$pset],$array)) {// Fritz!Box Anmeldedaten holen
  $cfg['host'] = $array[3];
  if(isset($cfg['preset'][$array[3]]))			// Voreingestellte Fritz!Boxen Erkennen und eintragen
   foreach($cfg['preset'][$array[3]] as $key => $var)
    $cfg[$key] = $var;
  if(@$array[1])
   $cfg['user'] = $array[1];
  if(@$array[2])
   $cfg['pass'] = $array[2];
  if(@$array[4])
   $cfg['port'] = $array[4];
  $pset++;
 }
 unset($cfg['preset']);					// Preset-Daten werden nicht mehr benötigt!

# Char ermitteln
 if($cfg['char'] == 'auto') {
  if(isset($_SERVER['LANG']) and preg_match('/((?:iso-)?8859-1)|(UTF-?8)/i',$_SERVER['LANG'],$var))
   $cfg['char'] = ($var[1]) ? 'ansi' : 'utf8';
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']))
   $cfg['char'] = 'oem';
  elseif(isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']))
   $cfg['char'] = 'utf8';
  else
   $cfg['char'] = 'ansi';
 }

# Optionen setzen
 if($_SERVER['argv'][$pmax-1]{0} == '-' and preg_match_all('/-(\w)(?::(\w+))?/',$_SERVER['argv'][$pmax-1],$array)) {
  $pmax--;
  foreach($array[1] as $key => $var) {
   if($var == 'h')
    $cfg['help'] = ($val = intval($array[2][$key]) and $val == $array[2][$key]) ? $val : true;
   if($var == 'd')
    $cfg['dbug'] = ($val = intval($array[2][$key]) and $val == $array[2][$key]) ? $val : true;
   if($var == 'c')
    $cfg['char'] = $array[2][$key];
   if($var == 't' and $array[2][$key] == $val = intval($array[2][$key]))
    $cfg['tout'] = $val;
   if($var == 'b' and $array[2][$key] == $val = intval($array[2][$key]))
    $cfg['sbuf'] = $val;
  }
 }

# Parameter auswerten
 if($pset < $pmax and preg_match('/^
	((?<bi>BoxInfo|bi)
	|(?<d>Dial|d)
	|(?<gip>G(et)?IP)
	|(?<i>I(nfo)?)
	|(?<k>K(onfig)?)
	|(?<rc>ReConnect|rc)
	|(?<sd>SupportDaten|sd)
	|(?<ss>(System)?S(tatu)?s)
	)$/ix',$_SERVER['argv'][$pset],$val)) {		## Modes mit und ohne Login ##
  $pset++;
  if(@$val['bi']) {					// Jason Boxinfo
   if(@$cfg['help'])
    echo out("$self fritz.box:port [BoxInfo|bi]\n\n"
	."Beispiele:\n"
	."$self boxinfo\n"
	."$self 169.254.1.1 bi\n");
   elseif($data = request('GET','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data,$array)) {
    $jason = array(
	'Name'		=> array('Modell',false),
	'HW'		=> array('Hardware-Version',false),
	'Version'	=> array('Firmware-Version',false),
	'Revision'	=> array('Firmware-Revision',false),
	'Serial'	=> array('MAC-Adresse (LAN)','/\w\w(?=\w)/$0:/'),
	'OEM'		=> array('Branding',false),
	'Lang'		=> array('Sprache',false),
	'Annex'		=> array('Annex (Festnetz)',false),
	'Lab'		=> array('Labor',false),
	'Country'	=> array('Land-Vorwahl',false),
	'Flag'		=> array('Flags',false));
    foreach($array[1] as $key => $var)
     $array[0][$key] = str_pad(((isset($jason[$var])) ? $jason[$var][0] : $var)." ",20,'.')." ".((isset($jason[$var]) and $jason[$var][1])
	? preg_replace(preg_replace('/^((.).+?(?<!\\\\)\2).*\2(\w*)$/','$1$3',$jason[$var][1]),
	preg_replace('/^(.).+?(?<!\\\\)\1(.*)\1\w*$/','$2',$jason[$var][1]),$array[2][$key]) : $array[2][$key]);
    echo out("\nBoxinfo:\n".implode("\n",$array[0])."\n");
   }
   else
    echo out("Keine Informationen erhalten\n");
  }
  elseif(@$val['gip']) {				// Get Extern IP
   if(@$cfg['help'])
    echo out("$self fritz.box:port [GetIP|gip]\n\n"
	."Beispiele:\n"
	."$self getip\n"
	."$self 169.254.1.1 gip\n");
   elseif($var = getexternalip())
    echo out("IPv4: $var\n");
   elseif($var = getsockerror())
    echo out($var);
   else
    echo out("Keine Externe IP-Adresse verfügbar\n");
  }
  elseif(@$val['i']) {					// Info
   if(@$cfg['help'])
    echo out("$self [Info|i]\n\n"
	."Beispiel:\n"
	."$self info\n"
	."$self i -d\n");
   elseif(@$cfg['dbug'])
    phpinfo();
   else
    echo out("$ver\n".php_uname()."\nPHP ".phpversion()."/".php_sapi_name()."\n\nFile: $script\nMD5:  ".md5_file($script)."\nSHA1: ".sha1_file($script)."\n");
  }
  elseif(@$val['rc']) {					// ReConnect
   if(@$cfg['help'])
    echo out("$self fritz.box:port [ReConnect|rc]\n\n"
	."Beispiele:\n"
	."$self reconnect\n"
	."$self 169.254.1.1 rc\n");
   else
    echo out(($var = forcetermination()) ? "Ok\n" : getsockerror(),1);
  } 
  elseif(@$val['ss']) {					// SystemStatus
   if(@$cfg['help'])
    echo out("$self fritz.box:port [SystemStatus|Status|ss] <supportcode>\n\n"
	."Beispiele:\n"
	."$self systemstatus\n"
	."$self 169.254.1.1 status\n"
	."$self ss \"FRITZ!Box Fon WLAN 7390-B-010203-040506-000000-000000-147902-840522-22574-avm-de\"\n");
   else
    echo out(supportcode(($pset < $pmax) ? $_SERVER['argv'][$pset] : false));
  }
  elseif(@$val['k']) {					// Konfig
   if(@$cfg['help'] or $pset == $pmax)
    echo out("$self user:pass@fritz.box:port [Konfig|k] [Funktion] <Datei> <Kennwort>\n\n"
	."Funktionen:\n"
	."ExPort         <Datei>  <Kennwort> - Konfig exportieren\n"
	."ExTrakt        <Ordner> <Kennwort> - Konfig entpackt anzeigen/exportieren\n"
	."File           [Datei]  <Ordner>   - Konfig-Infos aus Datei ausgeben\n"
	."ImPort         [Datei]  <Kennwort> - Konfig importieren\n"
	."ImPort-CalcSum [Datei]  <Kennwort> - Veränderte Konfig importieren\n\n"
	."Beispiele:\n"
	."$self password@fritz.box konfig export\n"
	."$self fritz.box konfig extrakt\n"
	."$self konfig file fritzbox.export\n"
	."$self username:password@fritz.box konfig import \"fritzbox.export\"\n"
	."$self 169.254.1.1 k ipcs \"FRITZ.Box Fon WLAN 6360 85.04.86_01.01.00_0100.export\"\n");
   elseif(preg_match('/^(			# 1:Alle
	|i(p|mport)(cs|-calcsum)?	# 2:Import 3:calcsum
	|e(p|xport)			# 4:Export
	|(et|(?:extra[ck]t))?		# 5:Extrakt
	|(f(?:ile)?)			# 6:File
		)$/ix',$_SERVER['argv'][$pset++],$mode)) {
    $file = ($pset < $pmax) ? $_SERVER['argv'][$pset++] : false;
    $pass = ($pset < $pmax) ? $_SERVER['argv'][$pset++] : false;
    if(($mode[2] or $mode[4] or $mode[5])) {
     if($sid = $cfg['sid'] = login()) {
      if(@$mode[4] or @$mode[5]) {			// Export & Extrakt
       if(is_dir($file)) {	// Current-Dir setzen
        chdir($file);
        $file = false;
        $mode[5] = true;
       }
	   echo out(cfgexport($file,$pass,@$mode[5]));
      }
      elseif($mode[2] and $file and is_file($file))
       cfgimport($file,$pass,$mode[3]);
      else
       echo out("$file kann nicht geöffnet werden!");
      logout($sid);
     }
     else
      echo out("Login fehlgeschlagen");
    }
    elseif($mode[6] and is_file($file) and $data = file_get_contents($file)) {
     if($pass and is_dir($pass))
      chdir($pass);
     echo out(($data = cfginfo($data,$pass)) ? $data : "Keine Konfig Export-Datei angegeben");
    }
    else
     echo out("Parameter-Ressourcen nicht gefunden");
   }
   else
    echo out("Unbekannter Funktionsangabe");
  }
  elseif(@$val['d']) {					// Dial
   if(@$cfg['help'] or $pset == $pmax)
    echo out("$self user:pass@fritz.box:port [Dial|d] [Rufnummer] <Telefon>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box dial 0123456789 50\n"
	."$self username:password@fritz.box dial \"#96*7*\"\n"
	."$self 169.254.1.1 d -\n");
   elseif($sid = $cfg['sid'] = login()) {
    echo out(dial($_SERVER['argv'][$pset++],(($pset < $pmax) ? $_SERVER['argv'][$pset++] : false)));
    logout($sid);
   }
   else
    echo out("Login fehlgeschlagen!");
  }
  elseif(@$val['sd']) {					// Supportdaten
   if(@$cfg['help'])
    echo out("$self user:pass@fritz.box:port [SupportDaten|sd] <filename>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box supportdaten support.txt\n"
	."$self 169.254.1.1 sd\n");
   elseif($sid = $cfg['sid'] = login()) {
    $text = supportdaten(($pset < $pmax) ? $_SERVER['argv'][$pset] : false);
    if($text !== true)
     echo out($text);
    logout($sid);
   }
   else
    echo out("Login fehlgeschlagen!");
  }
 }
 elseif(@$cfg['dbug'] and $cfg['dbug'] < 2)		// DEBUG0: $cfg ausgeben
  echo out(print_r($cfg,true));
 else
  echo out("$self user:pass@fritz.box:port [mode] <mode-parameter> ... <option>\n".((@$cfg['help']) ? <<<eof
\nModes:
BoxInfo      - Modell, Firmware-Version und MAC-Adresse ausgeben
Dial         - Rufnummer wählen(2)
GetIP        - Aktuelle externe IPv4-Adresse ausgeben(1)
Info         - FB-Tools Version, PHP Version, MD5/SHA1 Checksum 
Konfig       - Einstellungen Ex/Importieren(2)(3)
ReConnect    - Neueinwahl ins Internet(1)
SupportDaten - AVM-Supportdaten Speichern(2)
SystemStatus - Modell, Version, Laufzeiten, Neustarts und Status ausgeben(3)

(1) Aktiviertes UPnP erforderlich / (2) Anmeldung mit Logindaten erforderlich
(3) Teilweise ohne Fritz!Box nutzbar / [ ] Pflicht / < > Optional

Optionen:
-b:<Bytes>         - Buffergröße ($cfg[sbuf])
-c:<ansi|oem|utf8> - Kodierung der Umlaute ($cfg[char])
-d                 - Debuginfos
-h                 - Hilfe
-t:<Sekunden>      - TCP/IP Timeout ($cfg[tout])\n
eof
: "\nWeitere Hilfe bekommen Sie mit der -h Option\n"));

 if(@$GLOBALS['cfg']['dbug']) {				// Debuginfos ausgeben
  if($GLOBALS['cfg']['dbug'] === 9)			// Globals bei -d:9 ausgeben
   echo out("Globals:\n".print_r($GLOBALS,true));
  elseif($GLOBALS['cfg']['error'])			// Fehler bei -d ausgeben
   echo out("Debug:\n".print_r($GLOBALS['cfg']['error'],true));
 }
}

?>
