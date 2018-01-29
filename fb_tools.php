#!/usr/bin/php
<?php $ver = "fb_tools 0.13 (c) 25.06.2016 by Michael Engelke <http://www.mengelke.de>"; #(charset=iso-8859-1 / tabs=8 / lines=lf)

if(!isset($cfg)) {					// $cfg schon gesetzt?
 $cfg = array(
	'host'	=> 'fritz.box',		// Fritz!Box-Addresse
	'pass'	=> 'password',		// Fritz!Box Kennwort
	'user'	=> false,		// Fritz!Box Username (Optional)
	'port'	=> 80,			// Fritz!Box HTTP-Port (Normalerweise immer 80)
	'fiwa'	=> 100,			// Fritz!Box Firmware (Nur Intern)
	'upnp'	=> 49000,		// Fritz!Box UPnP-Port (Normalerweise immer 49000)
	'pcre'	=> 16*1024*1024,	// pcre.backtrack_limit
	'sbuf'	=> 4096,		// TCP/IP Socket-Buffergröße
	'tout'	=> 3,			// TCP/IP Socket-Timeout
	'wrap'	=> 0,			// Manueller Wortumbruch (Kein Umbruch 0) 
	'char'	=> 'auto',		// Zeichenkodierung der Console (auto/ansi/oem/utf8)
	'time'	=> 'Europe/Berlin',	// Zeitzone festlegen
	'help'	=> false,		// Hilfe ausgeben
	'dbug'	=> false,		// Debuginfos ausgeben
	'oput' => false,		// Ausgaben speichern
	'error' => array(),		// Fehlerlogs
	'preset'=> array(),		// Leere Benutzerkonfiguration
	'boxinfo'=>array(),		// Leere Boxinfo Daten
	'usrcfg'=> 'fb_config.php',	// Filename der Benutzerkonfiguration
 );
}
if(!function_exists('array_combine')) {			// http://php.net/array_combine
 function array_combine($key,$value) {
  $array = false;
  if(is_array($key) and is_array($value) and count($key) == count($value)) {
   $array = array();
   while(list($kk,$kv) = each($key) and list($vk,$vv) = each($value))
    $array[$kv] = $vv;
  }
  return $array;
 }
}
function ifset(&$x,$y=false) {				// Variabeln prüfen
 return (isset($x) and $x) ? (($y and is_string($x) and preg_match($y,$x,$z)) ? $z : !$y) : false;
}
function out($str,$mode=0) {				// Textconvertierung vor der ausgabe ($Mode: Bit 0 -> echo / Bit 1 -> autolf)
 global $cfg;
 if(is_array($str))
  $str = print_r($str,true);
 if(!($mode/(1<<1)%2) and preg_match('/\S$/D',$str))	// AutoLF
  $str .= "\n";
 if($cfg['oput'])					// Ausgabe speichern
  savedata($cfg['oput'],$str,'a+');
 if($cfg['wrap'])					// Wortumbruch
  $str = wordwrap($str,$cfg['wrap'],"\n",true);
 if($cfg['char'] == 'oem')
  $str = str_replace(array('ä','ö','ü','ß','Ä','Ö','Ü','§',"\n"),array(chr(132),chr(148),chr(129),chr(225),chr(142),chr(153),chr(154),chr(21),"\r\n"),$str);
 elseif($cfg['char'] == 'utf8')
  $str = utf8_encode($str);
 elseif($cfg['char'] == 'html')
  $str = str_replace(array('&','<','>','"',"'",'ä','ö','ü','ß','Ä','Ö','Ü'),
   array('&amp;','&lt;','&gt;','&quot;',"&#39;",'&auml;','&ouml;','&uuml;','&szlig;','&Auml;','&Ouml;','&Uuml;'),$str);
 return ($mode/(1<<0)%2) ? $str : print $str;
}
function dbug($str,$file=false) {			// Debug-Daten ausgeben/speichern
 global $cfg;
 $time = ($cfg['dbug']/(1<<2)%2) ? number_format(array_sum(explode(' ',microtime()))-$cfg['time'],2,',','.').' ' : '';
 if($cfg['dbug']/(1<<1)%2 and $file)			// Debug: Array in separate Datei sichern
  if(strpos($file,'#') and is_array($str))
   foreach($str as $key => $var)			// Debug: Array in mehrere separaten Dateien sichern
    savedata("debug-".str_replace('#',$key,$file).".txt",$time.((is_array($var)) ? print_r($var,true) : $var),'a+');
  else
   savedata("debug-$file.txt",$time.((is_array($str)) ? print_r($str,true) : $str),'a+');	// Alles in EINE Datei Sichern
 else {
  if(is_string($str)) {
   if(preg_match('/^\$(\w+)$/',$str,$var) and isset($GLOBALS[$var[1]]))
    $str = "$str => ".(is_array($GLOBALS[$var[1]]) ? print_r($GLOBALS[$var[1]],true) : $GLOBALS[$var[1]]);
   elseif(preg_match('/\S$/D',$str))			// AutoLF
    $str .= "\n";
  }
  elseif(is_array($str))
   $str = print_r($str,true);
  if($cfg['dbug']/(1<<1)%2)				// Debug: Ausgabe/Speichern
   savedata("debug.txt",$time.$str,'a+');
  else
   out($str);
 }
}
function request($method,$page='/',$body=false,$head=false,$host=false,$port=false) {	// HTTP-Request durchführen
 global $cfg;
 if(is_array($method))					// Restliche Parameter aus Array holen
  extract($method);
 foreach(array('host','port') as $var)			// Host & Port setzen
  if(!$$var)
   $$var = $cfg[$var];
 if(!$head)						// Head Initialisieren
  $head = $cfg['head'];
 if($mode = preg_match('/^(\w+)(?:-(\w+))?/',$method,$var)) {
  $method = strtoupper($var[1]);
  $mode = (isset($var[2])) ? $var[2] : (($var[1] == strtolower($var[1])) ? 'array' : false);	// Result-Modus festlegen
 }
 if($cfg['dbug']/(1<<5)%2)
  dbug("$host:$port ");
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
       elseif($k == 'filename')				// Weitere Angaben im Header
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
  if($cfg['dbug']/(1<<5)%2)				// Debug Request
   dbug("$method $page $head$body",'RequestPut');
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
  $GLOBALS['cfg']['error']['sock'][] = array("$host:$port",$errnr,$errstr);
 if($cfg['dbug']/(1<<6)%2)				// Debug Response
  dbug($rp,'RequestGet');
 return $fp;
}
function prcb_stu($x) {					// PREG_Replace_Callback Uppercase
 return strtoupper($x[0]);
}
function getsockerror() {				// Fehler bei Netzwerkverbindungen
 return ($var = end($GLOBALS['cfg']['error']['sock'])) ? "Fehler: $var[0] nicht gefunden!\n\n$var[2]\n" : false;
}
function response($xml,$pass,$page=false) {		// Login-Response berechnen
 $hash = false;
 if(preg_match('!<Challenge>(\w+)</Challenge>!',$xml,$var)) {
  $hash = "response=$var[1]-".md5(preg_replace('!.!',"\$0\x00","$var[1]-$pass"));
  if($page and $GLOBALS['cfg']['fiwa'] == 100)
   $GLOBALS['cfg']['fiwa'] = (substr($page,-4) == '.lua') ? '529' : '474';
 }
 return $hash;
}
function login($pass=false,$user=false) {		// In der Fritz!Box einloggen
 global $cfg;
 foreach(array('user','pass') as $var)			// User & Pass setzen
  if(!$$var)
   $$var = $GLOBALS['cfg'][$var];
 if($cfg['dbug']/(1<<0)%2)
  dbug("Login ".(($user) ? "$user@" : "")."$cfg[host]");
 if($data = request('GET','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data,$array)) {	// BoxInfos holen
  if($cfg['dbug']/(1<<4)%2)
   dbug($array);
   $cfg['boxinfo'] = array_combine($array[1],$array[2]);
  if(preg_match('/^\d+\.0*(\d+?)\.(\d+)$/',$cfg['boxinfo']['Version'],$var))	// Firmware-Version sichern
   $cfg['fiwa'] = $var[1].$var[2];
 }
 $sid = false;
 $page = "/login_sid.lua";
 if($rp = request('GET',$page)) {			// Login lua ab 05.29
  if(($auth = response($rp,$pass,$page)) and preg_match('/<SID>(?!0{16})(\w+)<\/SID>/',$rp = request('POST',$page,(($user) ? "$auth&username=$user" : $auth)),$var)) {
   $sid = $var[1];
  }
  else {						// Login cgi ab 04.74
   $page = "/cgi-bin/webcm";
   $data = "getpage=../html/login_sid.xml";
   if(!$rp or !$auth = response(($rp),$pass,$page))
    $auth = response(request('GET',"$page?$data"),$pass,$page);
    if($auth and preg_match('/<SID>(\w+)<\/SID>/',request('POST',$page,"$data&login:command/$auth"),$var)) {
    $sid = (preg_match('/0{16}/',$var[1])) ? false : $var[1];
    if($cfg['fiwa'] == 100)
     $cfg['fiwa'] = 474;
   }
   elseif($cfg['fiwa'] == 100) {			// Login classic
    if(!preg_match('/Anmeldung/',request('POST',$page,"login:command/password=$pass")))
     $sid = true;
   }
  }
 }
 return $cfg['sid'] = $sid;				// SID zurückgeben
}
function logout($sid) {					// Aus der Fritz!Box ausloggen
 if($GLOBALS['cfg']['dbug']/(1<<0)%2)
  dbug("Logout ".$GLOBALS['cfg']['host']);
 if(is_string($sid) and $sid)				// Ausloggen
  request('GET',(($GLOBALS['cfg']['fiwa'] < 529) ? "/cgi-bin/webcm" : "/login_sid.lua"),"security:command/logout=1&logout=1&sid=$sid");
}
function supportcode($str = false) {			// Supportcode aufschlüsseln
 if(!$str and !$str = request('GET','/cgi-bin/system_status'))
  return getsockerror();
 return (preg_match('!
	([^<>]+?)-
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
	.((ifset($array[19])) ? "Sprache: ".strtr($array[19],array('de' => 'Deutsch', 'en' => 'Englisch'))."\n" : '')
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
function upnprequest($page,$ns,$rq,$exp=false) {	// UPnP Request durchführen
 return (function_exists('utf8_encode') and $rp = request(array(
	'method' => 'POST',
	'page' => $page,
	'body' => utf8_encode("<"."?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
		."<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" s:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">\n"
		."<s:Body><u:$rq xmlns:u=$ns /></s:Body>\n</s:Envelope>"),
	'head' => array_merge($GLOBALS['cfg']['head'],array('content-type' => 'text/xml; charset="utf-8"', 'soapaction' =>  "\"$ns#$rq\"")),
	'port' => $GLOBALS['cfg']['upnp']))) ? (($exp) ? ((preg_match("/<$exp>(.*?)<\/$exp>/",$rp,$var)) ? $var[1] : false) : $rp) : false;
}
function getupnppath($urn) {				// Helper für UPnP-Requests
 return ($rp = request(array('method' => 'GET', 'page' => '/igddesc.xml', 'port' => $GLOBALS['cfg']['upnp']))
	and preg_match("!<(service)>.*?<(serviceType)>(urn:[^<>]*".$urn."[^<>]*)</\\2>.*?<(controlURL)>(/[^<>]+)</\\4>.*?</\\1>!s",$rp,$var))
	? array($var[3],$var[5]) : false;
}
function getexternalip() {				// Externe IPv4-Adresse über UPnP ermitteln
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'GetExternalIPAddress','NewExternalIPAddress')) ? $var : false;
}
function forcetermination() {				// Internetverbindungen über UPnP neu aufbauen
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'ForceTermination','NewExternalIPAddress')) ? $var : false;
}
function savedata($file,$data,$mode='w') {		// Daten in Datei speichern
 if($file and $fp = fopen($file,$mode)) {
  fputs($fp,$data);
  fclose($fp);
  $fp = true;
 }
 return $fp;
}
function saverpdata($file,$data,$name) {		// HTTP-Downloads in Datei speichern
 $file = preg_replace('/[<>\[\]:\/\\\\"*?|]/','_',($file) ? $file : ((@preg_match('/filename="(.*)"/',$data['Content-Disposition'],$var)) ? $var[1] : $name));
 $cfg['file'] = $file;
 return savedata($file,$data[1]);
}
function supportdaten($file,$sid=false) {		// Supportdaten erstellen
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $array = array();
 if($sid and $sid !== true)
  $array['sid'] = $sid;
 $array['SupportData'] = '';
 return ($data = request('POST-array','/cgi-bin/firmwarecfg',$array))
  ? ((saverpdata($file,$data,"supportdaten.txt")) ? true : "$cfg[file] kann nicht geschrieben werden!") : "Keine Daten erhalten!";
}
function dial($dial,$port=false,$sid=false) {		// Wahlhilfe
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $dial = preg_replace('/[^\d*#]/','',$dial);
 $rdial = urlencode($dial);
 $port = ($port) ? preg_replace('/\D+/','',$port) : false;
 if($GLOBALS['cfg']['fiwa'] >= 530) {
  if($port) {
   if($GLOBALS['cfg']['dbug']/(1<<0)%2)
    dbug("Dial: Ändere Anruf-Telefon auf $port");
   request('POST',"/fon_num/dial_fonbook.lua","clicktodial=on&port=$port&btn_apply=&sid=$sid");
  }
  if($GLOBALS['cfg']['dbug']/(1<<0)%2)
   dbug("Dial: ".(($rdial) ? "Wähle $dial" : "Auflegen"));
  request('GET',"/fon_num/fonbook_list.lua",(($rdial == '') ? "hangup=&orig_port=$port" : "dial=$rdial")."&sid=$sid");
 }
 else {
  request('POST',"/cgi-bin/webcm","telcfg:settings/UseClickToDial=1"
	.(($rdial == '') ? "&telcfg:command/Hangup=" : "&telcfg:command/Dial=$rdial")
	.(($port) ? "&telcfg:settings/DialPort=$port" : "")."&sid=$sid");
  if($GLOBALS['cfg']['dbug']/(1<<0)%2)
   dbug("Dial: ".(($rdial) ? "Wähle $dial".(($port) ? " für Telefon $port" : "") : "Auflegen"));
 }
}
function cfgexport($file,$pass=false,$mode=false,$decrypt=false,$sid=false) {	// Konfiguration Exportieren
 global $cfg;
 $body = array('ImportExportPassword' => $pass, 'ConfigExport' => false);
 if(!$sid) {
  $sid = $cfg['sid'];
  $body = array_merge(array('sid' => $cfg['sid']),$body);
 }
 if($data = request('POST-array','/cgi-bin/firmwarecfg',$body)) {	// Konfig aus der Fritz!Box holen
  $txt = '';
  if($decrypt and $pass and $cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {	// Kennwörter entschlüsseln
   $data[1] = konfigdecrypt($data[1],$pass,$sid);
   $txt = showaccessdata($data[1]);
  }
  return ($mode)? cfginfo($data[1],$file).$txt				// Konfig anzeigen
		: ((saverpdata($file,$data,"fritzbox.export"))		// Konfig speichern
			? $txt : "$cfg[file] kann nicht geschrieben werden!");
 }
 else
  return "Keine Daten erhalten!";
}
function cfgcalcsum($data) {				// Checksumme für die Konfiguration berechnen
 if(preg_match_all('/^(\w+)=(\S+)\s*$|^(\*{4}) (?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*(.*?)\3 END OF FILE \3\s*$/sm',$data,$array)) {
  if($GLOBALS['cfg']['dbug']/(1<<4)%2)
   dbug($array,'CfgCalcSum-#');
  foreach($array[4] as $key => $var) {
   if($array[1][$key])
    $array[0][$key] = $array[1][$key].$array[2][$key]."\0";
   elseif($var == 'CFG')
    $array[0][$key] = $array[5][$key]."\0".stripcslashes(str_replace("\r",'',substr($array[6][$key],0,-1)));
   elseif($var == 'BIN')
    $array[0][$key] = $array[5][$key]."\0".pack('H*',preg_replace('/\W+/',"",$array[6][$key]));
  }
 }
 return ($array and preg_match('/(?<=^\*{4} END OF EXPORT )[A-Z\d]{8}(?= \*{4}\s*$)/m',$data,$key,PREG_OFFSET_CAPTURE))
	? array($key[0][0],$var = str_pad(sprintf('%X',crc32(join('',$array[0]))),8,0,STR_PAD_LEFT),substr_replace($data,$var,$key[0][1],8)) : false;
}
function cfgimport($file,$pass=false,$mode=false,$sid=false) {	// Konfiguration importieren
 if(is_file($file) and ($data = file_get_contents($file)) || is_dir($file) and ($data = cfgmake($file))) {
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
function cfginfo($data,$mode) {				// Konfiguration in Einzeldateien sichern
 if(preg_match_all('/^(?:
	\*{4}\s(.*?)\sCONFIGURATION\sEXPORT		# Fritzbox-Modell
	|(\w+=\S+)					# Variablen
	)\s*$
	|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?\r?\n(.*?)\r?\n^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$/msx',$data,$array) and $array[1][0] and $crc = cfgcalcsum($data)) {
  $list = $val = $vars = array();
  $mstr = $mlen = array(0,0);
 if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)			// Debugdaten Speichern
  dbug($array,'CfgInfo-#');
  foreach($array[3] as $key => $var)			// Config-Dateien aufteilen
   if($var) {
    $bin = ($array[3][$key] == 'CFG') ? str_replace(array("\r","\\\\"),array("","\\"),$array[5][$key]) : pack('H*',preg_replace('/\W+/',"",$array[5][$key]));
    $list[] = array($array[3][$key],$array[4][$key],number_format(strlen($bin),0,",","."));
    if($mode)
     savedata($array[4][$key],$bin);
    unset($array[2][$key]);
   }
   elseif($array[2][$key] and preg_match('/^(\w+)=(.*)$/',$array[2][$key],$var))
    $vars[$var[1]] = $var[2];
   else
    unset($array[2][$key]);
  $data = preg_replace('/^(\*{4}\s(?:CRYPTED)?(?:CFG|BIN)FILE:\S+\s*?\r?\n).*?\r?\n(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?)$/msx','$1$2',$data);
  $file = "pattern.txt";
  $list[] = array("PAT",$file,number_format(strlen($data),0,",","."));
  if($mode)
   savedata($file,$data);
  foreach($list as $key => $var) {			// Maximale Längen ermitteln
   $c = ($key < count($list)/2) ? 0 : 1;
   $mstr[$c] = max($mstr[$c],strlen($var[1]));
   $mlen[$c] = max($mlen[$c],strlen($var[2]));
  }
  for($a=0;$a<count($list);$a++)			// Liste zusammenstellen
   if(@$var=$list[(($a-$a%2)/2)+floor(count($list)%2+count($list)/2)*($a%2)])
    $val[$a-$a%2] = ((isset($val[$a-$a%2])) ? $val[$a-$a%2] : '').$var[0].": ".str_pad($var[1],$mstr[$a%2]," ")." ".str_pad($var[2],$mlen[$a%2]," ",STR_PAD_LEFT)." Bytes   ";
  $list = "\nModell:   {$array[1][0]}\n";
  if(isset($vars['FirmwareVersion']))
   $list .= "Firmware: $vars[FirmwareVersion]\n";
  return $list."Checksum: $crc[0] (".((preg_match('/^\${4}\w+$/',$vars['Password'])) ? (($crc[0] == $crc[1]) ? "OK" : "Inkorrekt! - Korrekt: $crc[1]") : "Nicht mehr gültig").")\n\n"
	.implode("\n",$val)."\n";
 }
 else
  return false;
}
function cfgmake($dir,$mode=false,$file=false) {	// Konfiguration wieder zusammensetzen
 if($data = file_get_contents("$dir/pattern.txt") and preg_match('/^\*{4}\s+FRITZ!/m',$data,$array)) {
  $GLOBALS['val'] = $dir;
  $data = preg_replace_callback('/(^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?(\r?\n))(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$)/m','prcb_cfgmake',$data);
  if(preg_match('/^\*{4}\s(.*?)\sCONFIGURATION\sEXPORT.*?FirmwareVersion=(\S+)/s',$data,$array) and $crc = cfgcalcsum($data)) {
   $val = "Modell:   $array[1]\nFirmware: $array[2]\nChecksum: $crc[0] ";
   $val .= (($crc[0] == $crc[1]) ? "(OK)" : "Inkorrekt! - Korrekt: $crc[1]")."\n";
   $data = ($mode) ? $crc[2] : $data;
   savedata($file,$data);
   return ($file) ? $val : $data;
  }
 }
 return false;
}
function prcb_cfgmake($a,$b='') {			// Helper für Preg_Replace CfgMake
  if(file_exists("$GLOBALS[val]/$a[3]"))
   $b = file_get_contents("$GLOBALS[val]/$a[3]");
  return $a[1].(($a[2] == 'BIN') ? wordwrap(strtoupper(implode('',unpack('H*',$b))),80,$a[4],CUT) : str_replace("\\","\\\\",$b)).$a[4].$a[5];
}
function konfigdecrypt($data,$pass,$sid=false) {	// Konfig-Datei mit Fritz!Box entschlüsseln
 global $cfg;
 if(preg_match_all('/^\s*([\w-]+)\s*=\s*("?)(\${4}\w+)\2;?\s*$/m',$data,$array) and ($var = array_search('Password',$array[1])) !== false) {
  if($cfg['dbug']/(1<<4)%2)
   dbug($array,'KonfigDeCrypt');
  if(preg_match('/^boxusers\s\{\s*^\s{8}users(\s\{.*?^\s{8}\}$)/smx',$k = str_replace('#0',$array[3][$var],gzinflate(base64_decode("
	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
	gsOx/xSA/wE="))),$v))				// Stark gekürze und leere Fritz!Box 7490 Konfig
   $export = array($k,$v[1]);
  $plain = array($array[3][$var] => $pass);		// Entschlüsselte Einträge vorbereiten
  unset($array[3][$var]);
  $list = array_unique($array[3]);			// Doppelte Einträge entfernen
  if($cfg['dbug']/(1<<0)%2)
   dbug("konfigdecrypt: ".(count($list)+1)." verschiedene verschlüsselte Einträge gefunden!");
  if(!$sid)						// Sid sicherstellen
   $sid = $cfg['sid'];
  $pregimport = array(					// Decode-Liste
	'Internetzugangsdaten'	=> array(1,0,'/^Benutzer:\s(.*?)(?:,\sAnbieter:\s.*?)?$/'),
	'Dynamic DNS'		=> array(2,1,'/(?<=Domainname:\s|Benutzername:\s)(.*?)(?=,\s|$)/'),
	'PushService'		=> array(1,3,'/^E-Mail-Empf.{1,2}nger:\s(.*?)$/'),
	'MyFRITZ!'		=> array(1,4,'/^(.*?)$/'),
	'FRITZ!Box-Benutzer'	=> array(15,5,'/(?<=^|,\s)(.*?)(?=,\s|$)/'),
  );
  $buffer = array();
  while(count($list)) {					// Alle Verschlüsselte Einträge durchlaufen
   $import = $export[0];
   $buffer = array_values($buffer);
   while(count($list) and count($buffer) < 20)		// Die ersten 20 Einträge sichern
    $buffer[] = array_shift($list);
   $a = 1;						// Import-Buffer füllen
   $v = array();
   foreach($buffer as $var)
    if($a < 6)
     $import = str_replace('#'.$a++,$var,$import);
    else
     $v[] = str_replace('#7',$var,str_replace('#6',4+$a++,$export[1]));
   $import = preg_replace('/(^boxusers\s\{\s*^\s{8}users)\s\{.*?^\s{8}\}$/smx',"$1".str_replace('$','\$',implode('',$v)),$import);
   if($var = cfgcalcsum($import))			// Checksum berechnen
    $import = $var[2];
   if($var = request('POST','/cgi-bin/firmwarecfg',array('sid' => $sid, 'ImportExportPassword' => $pass,
	'ConfigTakeOverImportFile' => array('filename' => 'fritzbox.export', 'Content-Type' => 'application/octet-stream', '' => $import), 'apply' => false))
	and !preg_match('/cfg_nok/',$var)) {
    if($getdata = (($cfg['fiwa'] < 650) ? request('GET',"/system/cfgtakeover_edit.lua?sid=$sid&cfg_ok=1") : request('POST',"/data.lua","xhr=1&sid=$sid&lang=de&no_sidrenew=&page=cfgtakeover_edit"))) {
     if(preg_match_all('/^\s*\["add\d+_text"\]\s*=\s*"([^"]+)",\s*$.*?^\s*\["gui_text"\]\s*=\s*"([^"]+)",\s*$/sm',$getdata,$match))
      $match[2] = array_flip($match[2]);
     elseif(preg_match_all('/<label for="uiCheckcfgtakeover\d+">(.*?)\s*<\/label>\s*<span class="addtext">(.*?)\s*<br>\s*<\/span>/',$getdata,$match))
      $match = array(1 => $match[2], 2 => array_flip($match[1]));
     if($match) {					// Decodierte Kennwörter gefunden
      foreach($pregimport as $key => $var)
       if(isset($match[2][$key]) and preg_match_all($var[2],$match[1][$match[2][$key]],$array))
        foreach($array[1] as $k => $v)
         if(isset($buffer[$var[1] + $k])) {		// Kennwort sichern
          $plain[$buffer[$var[1] + $k]] = str_replace('"','\\\\"',html_entity_decode(((function_exists('utf8_decode')) ? utf8_decode($v) : $v)));
          unset($buffer[$var[1] + $k]);
         }
     }
     else
      return false;
    }
    else
     return false;
   }
   else
    return false;
   if($cfg['dbug']/(1<<0)%2)
    dbug((count($list)) ? floor(count($plain)/(count($list)+count($plain))*100)."% entschlüsselt..." : "100% - Ersetze ".count($plain)." entschlüsselte Einträge...");
  }
  return str_replace(array_keys($plain),array_values($plain),$data);
 }
 return false;
}
function showaccessdata($data) {			// Die Kronjuwelen aus Konfig-Datei heraussuchen
 $config = array();					// (?!\${4}\w+)
 foreach(array(
  'UMTS,UMTS-Stick' =>
   '^ar7cfg\s\{\s*$.*?^\s{8}serialcfg\s\{\s*$.*?number\s*=\s*"(?!\${4}\w+)(?<number>.*?)";\s*
   provider\s*=\s*"(?!\${4}\w+)(?<provider>.*?)";\s*username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";',
  'DSL,DSL' => '^ar7cfg\s\{\s*$.*?^\s{8}targets\s\{\s*$.*?^\s{16}local\s\{\s*$\s*username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*
   passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";',
  'IPv6,IPv6' => '^ipv6\s\{\s*$.*?^\s{8}sixxs\s\{\s*$.*?ticserver\s*=\s*"(?!\${4}\w+)(?<ticserver>.*?)";\s*username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*
   passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";\s*tunnelid\s*=\s*"(?!\${4}\w+)(?<tunnelid>.*?)";',
  'DDNS,DynamicDNS' => '^ddns\s\{\s*$.*?^\s{8}accounts\s\{\s*$.*?domain\s*=\s*"(?!\${4}\w+)(?<domain>.*?)";\s*
   username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";',
  'PUSH,eMail' => array('^emailnotify\s\{\s*$.*?From\s*=\s*"(?!\${4}\w+)(?<from>.*?)";\s*To\s*=\s*"(?!\${4}\w+)(?<to>.*?)";\s*
   SMTPServer\s*=\s*"(?!\${4}\w+)(?<smtp>.*?)";\s*accountname\s*=\s*"(?!\${4}\w+)(?<account>.*?)";\s*passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";\s*
   ^(?<array>.*?)^\}\s*$','^\s*(\w+)\s\{\s*$|^\s*(To|arg\d*)\s*=\s*"(.*?)";\s*$'),
  'MYFRITZ,MyFRITZ!' => '^jasonii\s\{\s*$.*?user_email\s*=\s*"(?!\${4}\w+)(?<user_email>.*?)";\s*user_password\s*=\s*"(?!\${4}\w+)(?<user_password>.*?)";\s*
   box_id\s*=\s*"(?!\${4}\w+)(?<box_id>.*?)";\s*box_id_passphrase\s*=\s*"(?!\${4}\w+)(?<box_id_passphrase>.*?)";\s*
   dyn_dns_name\s*=\s*"(?!\${4}\w+)(?<dyn_dns_name>.*?)";',
  'WEBUI,FRITZ!Box-Benutzer-Oberfläche' => '^webui\s\{\s*username\s=\s"(?!\${4}\w+)(?<username>.*?)";\s*password\s=\s"(?!\${4}\w+)(?<password>.*?)";',
  'WEBSRV,Fernwartung' => '^websrv\s\{\s*$.*?users\s\{\s*username\s=\s"(?!\${4}\w+)(?<username>.*?)";\s*passwd\s=\s"(?!\${4}\w+)(?<passwd>.*?)";',
  '_TR069CFG,TR-069-Fernkonfiguration' => array('^tr069cfg\s\{\s*$.*?(?<array>.*?)^\}\s*$',
   '^\s*(?<key>url|username|password)\s*=\s*"(?<value>.+?)";\s*$'),
  '_WLANCFG,WLAN' => array('^wlancfg\s\{\s*$.*?(?<array>.*?)^\}\s*$',
   '^\s*(?<key>(?:guest_)?ssid(?:_scnd)?|key_value\d|(?:guest_)?pskvalue|sta_key_value\d|wps_pin)\s*=\s*"(?<value>.+?)";\s*$'),
  '_T_MEDIA,Telekom-Mediencenter' => array('^t_media\s\{\s*$.*?(?<array>.*?)^\}\s*$',
   '^\s*(?<key>(?:refresh|access)token)\s*=\s*"(?<value>.+?)";\s*$'),
  '_GPM,Google-Play-Music' => array('^gpm\s\{\s*$.*?(?<array>.*?)^\}\s*$',
   '^\s*(?<key>emailaddress|password|partition|servername)\s*=\s*"(?<value>.+?)";\s*$'),
  'USERS,FRITZ!Box-Benutzer' => array('^boxusers\s\{\s*$(?<array>.*?)^\}\s*$',
   '\s*\{[^\0]*?^\s*name\s=\s"(?!\${4}\w+)(?<name>.*?)";\s*(?:email\s=\s"(?!\${4}\w+)(?<email>.*?)";\s*)?(passw(?:or)?d)\s=\s"(?!\${4}\w+)(?<passwd>.*?)";'),
  'VOIP,InternetTelefonie' => array('^voipcfg\s\{\s*$(?<array>.*?)^\}\s*$','ua\d+\s\{[^\0]*?username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*
   authname\s*=\s*"(?!\${4}\w+)(?<authname>.*?)";\s*passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";\s*registrar\s*=\s*"(?!\${4}\w+)(?<registrar>.*?)";[^\0]*?
   name\s*=\s*"(?!\${4}\w+)(?<name>.*?)";[^\0]*?
   stunserver\s*=\s*"(?!\${4}\w+)(?<stunserver>.*?)";\s*stunserverport\s*=\s*(?!\${4}\w+)(?<stunserverport>.*?);[^\0]*?'),
  'VOIPTEL,IP-Telefon' => array('^voipcfg\s\{\s*$.*?^\s{8}extensions\s*(?<array>\{\s*$.*?^\s{8}\}\s*$)',
   'username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*authname\s*=\s*"(?!\${4}\w+)(?<authname>.*?)";\s*passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";\s*
   (?:clientid\s*=\s*"(?!\${4}\w+)(?<clientid>.*?)";\s*)?extension_number\s*=\s*"?(?!\${4}\w+)(?<extensionnumber>.*?)"?;'),
  'ONLINETEL,Online-Telefonbuch' => array('^voipcfg\s\{\s*$.*?^\s{8}onlinetel\s*(?<array>\{\s*$.*?^\s{8}\}\s*$)',
   'url\s*=\s*"(?!\${4}\w+)(?<url>.*?)";\s*serviceid\s*=\s*"(?!\${4}\w+)(?<serviceid>.*?)";\s*username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*
    passwd\s*=\s*"(?!\${4}\w+)(?<passwd>.*?)";\s*refreshtoken\s*=\s*"(?!\${4}\w+)(?<refreshtoken>.*?)";\s*
    accesstoken\s*=\s*"(?!\${4}\w+)(?<accesstoken>.*?)";[^\0]*?pbname\s*=\s*"(?!\${4}\w+)(?<pbname>.*?)";'),
  'WEBDAV,Onlinespeicher' => '^webdavclient\s\{\s*$.*?host_url\s*=\s*"(?!\${4}\w+)(?<host_url>.*?)";\s*username\s*=\s*"(?!\${4}\w+)(?<username>.*?)";\s*
   password\s*=\s*"(?!\${4}\w+)(?<password>.*?)";',
  'VPNCFG,Virtual-Privat-Network' => array('^vpncfg\s\{\s*$.*?^\s{8}connections\s*{\s*$(?<array>.*?)^\s{8}\}\s*$',
   '\s*name\s*=\s*"(?!\${4}\w+)(?<name>.*?)";[^\0]*?(?:localid\s\{\s*fqdn\s=\s*"(?!\${4}\w+)(?<localid>.*?)";\s*\}\s*)?
   remoteid\s\{\s*(?:user_)?fqdn\s=\s*"(?!\${4}\w+)(?<remoteid>.*?)";\s*\}[^\0]*?key\s*=\s*"(?!\${4}\w+)(?<key>.*?)";')
) as $key => $var) {
  if(is_array($var)) {
   $reg = $var[0];
   $val = $var[1];
  }
  else
   $reg = $var;
  $key = explode(',',$key);
  if(preg_match("/$reg/mixs",str_replace('\\\\"','"',$data),$match)) {		// Hauptdaten suchen
   if($GLOBALS['cfg']['dbug']/(1<<4)%2)
    dbug($match,'ShowAccessData-Main');
   foreach($match as $k => $v) {
    if(preg_match('/^(?:(array)|(\D+))$/',$k,$w) and $w[1] and preg_match_all("/$val/mix",$v,$x)) {	// Unterdaten suchen
     if($GLOBALS['cfg']['dbug']/(1<<4)%2)
      dbug($x,'ShowAccessData-Sub');
     foreach($x[1] as $y => $z)				// Spezielle zusammenstellung
      if($key[0] == 'PUSH' and $z)
       $w = $z;
      elseif($key[0] == 'PUSH' and $x[3][$y])
       $config[$key[1]][$w][$x[2][$y]] = strtr($x[3][$y],array('\\"' => '"'));
      elseif($key[0] == 'USERS')
       $config[$key[1]][$x[1][$y]] = array('email' => $x[2][$y], $x[3][$y] => $x[4][$y]);
      elseif($key[0] == 'VOIP' and $x['name'][$y]) 
       $config[$key[1]][$x['name'][$y]] = array('username' => $x['username'][$y], 'authname' => $x['authname'][$y], 'passwd' => $x['passwd'][$y],
        'registrar' => $x['registrar'][$y], 'stunserver' => (($x['stunserver'][$y]) ? $x['stunserver'][$y].":".$x['stunserverport'][$y] : false));
      elseif($key[0] == 'VOIPTEL') 
       $config[$key[1]][$x['extensionnumber'][$y]] = array('username' => $x['username'][$y], 'authname' => $x['authname'][$y], 'passwd' => $x['passwd'][$y],
        'clientid' => $x['clientid'][$y]);
      elseif($key[0] == 'ONLINETEL') 
       $config[$key[1]][$x['pbname'][$y]] = array('url' => $x['url'][$y], 'serviceid' => $x['serviceid'][$y], 'username' => $x['username'][$y],
        'passwd' => $x['passwd'][$y], 'refreshtoken' => $x['refreshtoken'][$y], 'accesstoken' => $x['accesstoken'][$y]);
      elseif($key[0] == 'VPNCFG') 
       $config[$key[1]][$x['name'][$y]] = array('localid' => $x['localid'][$y], 'remoteid' => $x['remoteid'][$y], 'key' => $x['key'][$y]);
      elseif(substr($key[0],0,1) == '_')
       $config[$key[1]][$x['key'][$y]] = $x['value'][$y];
    }
    elseif($w and !$w[1] and $v)			// Einfache Zusammenstellung
     $config[$key[1]][$k] = $v;
   }
  }
 }
 if($GLOBALS['cfg']['dbug']/(1<<4)%2)
  dbug($config,'ShowAccessData');
 $text = '';						// Array in Text Umwandeln
 foreach($config as $key => $var) {
  $text .= "\n$key\n";
  $kl = max(array_map('strlen',array_keys($var)));
  foreach($var as $k => $v) {
   $text .= " ".str_pad($k,$kl)." -> ";
   if(is_array($v)) {
    $val = array();
    foreach($v as $kk => $vv)
     if($vv)
      $val[] = "$kk=$vv";
    $v = implode(", ",$val);
   }
   $text .= (($GLOBALS['cfg']['wrap']) ? wordwrap($v,$GLOBALS['cfg']['wrap']-($kl+6),str_pad("\n",$kl+6," "),true) : $v)."\n";
  }
 }
 return $text;
}

# Eigentlicher Programmstart

if(isset($argv) and count($argv) > 0 and preg_match('/^(\w+) ([\d.]+) \(c\) (\d\d)\.(\d\d)\.(\d{4}) by ([\w ]+?) *<([.:\/\w]+)>$/',$ver,$ver)) { ## CLI-Modus ##
 $ver[2] = floatval($ver[2]);				// fb_tools Version 
 $ver[] = intval($ver[5].$ver[4].$ver[3]);		// fb_tools Datum
 if(!$script = realpath($argv[0]))			// Pfad zum Scipt anlegen
  $script = realpath($argv[0].".bat");			// Workaround für den Windows-Sonderfall
 $self = basename($script);
 $ext = preg_replace('/\W+/','',pathinfo($script,PATHINFO_EXTENSION)); // Extension für Unix/Win32 unterscheidung
 $cfg['head'] = array('Useragent' => "$self $ver[2] ".php_uname()." PHP ".phpversion()."/".php_sapi_name());	// UserAgent 
 foreach(array(".",$script) as $var) {			// Benutzerkonfig suchen
  $var = realpath($var);
  if(is_file($var))
   $var = dirname($var);
  if(is_dir($var))
   $var .= "/".basename($cfg['usrcfg']);
  if(file_exists($var)) {				// Benutzerkonfig gefunden und laden
   include $var;
   break;
  }
 }
 if(@ini_get('pcre.backtrack_limit') < $cfg['pcre']) 	// Bug ab PHP 5 beheben (Für Große RegEx-Ergebnisse)
  @ini_set('pcre.backtrack_limit',$cfg['pcre']);
 if($cfg['time'])					// Zeitzone festlegen
  @ini_set('date.timezone',$cfg['time']);
 $cfg['time'] = array_sum(explode(' ',microtime()));	// Startzeit sichern
 $pmax = count($argv);
 $pset = 1;	// Optionszähler
 if($pset+1 < $pmax and @preg_match('/^
	(?:([^:]+):)?
	(?:([^@]+)@)?
	([\w.-]+\.[\w.-]+|\[[a-f\d:]+\]|'.strtr(preg_quote(implode("\t",array_keys($cfg['preset']))),"\t",'|').')
	(?::(\d{1,5}))?
		$/ix',$argv[$pset],$array)) {		// Fritz!Box Anmeldedaten holen
  $cfg['host'] = $array[3];
  if(isset($cfg['preset'][$array[3]]))			// Voreingestellte Fritz!Boxen Erkennen und Eintragen
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
  if($var = ifset($_SERVER['LANG'],'/(UTF-?8)|((?:iso-)?8859-1)/i'))
   $cfg['char'] = ($var[1] and function_exists('utf8_encode')) ? 'utf8' : 'ansi';
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']))
   $cfg['char'] = 'oem';
  elseif(isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']) and function_exists('utf8_encode'))
   $cfg['char'] = 'utf8';
  else
   $cfg['char'] = 'ansi';
 }

# Optionen setzen
 if($argv[$pmax-1]{0} == '-' and preg_match_all('/-(\w)(?::([\w.]+))?/',$argv[$pmax-1],$array)) {
  $pmax--;
  foreach($array[1] as $key => $var) {
   if($var == 'h')
    $cfg['help'] = ($val = intval($array[2][$key]) and $val == $array[2][$key]) ? $val : true;
   if($var == 'd')
    $cfg['dbug'] = ($val = intval($array[2][$key]) and $val == $array[2][$key]) ? $val : true;
   if($var == 'w')
    $cfg['wrap'] = ($val = intval($array[2][$key]) and $val == $array[2][$key]) ? $val : 80;
   if($var == 'c')
    $cfg['char'] = $array[2][$key];
   if($var == 't' and $array[2][$key] == $val = intval($array[2][$key]))
    $cfg['tout'] = $val;
   if($var == 'b' and $array[2][$key] == $val = intval($array[2][$key]))
    $cfg['sbuf'] = $val;
   if($var == 'o' and @$array[2][$key])
    $cfg['oput'] = $array[2][$key];
  }
 }

# Consolen Breite automatisch ermitteln
 if(!$cfg['wrap'] and isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']))
  $cfg['wrap'] = (int)exec('tput cols');

# PHP-Fehler Protokollieren
 if($cfg['dbug']/(1<<7)%2)
  set_error_handler(create_function('$no,$str,$file,$line','
  $a = preg_split("/\s+/","ERROR WARNING PARSE NOTICE CORE_ERROR CORE_WARNING COMPILE_ERROR COMPILE_WARNING
	USER_ERROR USER_WARNING USER_NOTICE STRICT RECOVERABLE_ERROR DEPRECATED USER_DEPRECATED UNKNOWN");
  foreach($a as $b => $c)
   if($no == pow(2,$b))
    break;
  $a = "$str on line $line";
  $b = &$GLOBALS["cfg"]["error"][$c][$file];
//  $b["backtrace"][] = debug_backtrace();
  if(!isset($a,$b) or array_search($a,$b) === false)
   $b[] = $a;
  return false;'));

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
	|(?<ug>UpGrade|ug)
	)$/ix',$argv[$pset++],$val)) {			## Modes mit und ohne Login ##
  if($cfg['dbug']/(1<<3)%2) {				// Debug Parameter
   dbug('$argv');
   dbug($val);
  }
  if(ifset($val['bi']) and $val['bi']) {		// Jason Boxinfo
   if(@$cfg['help'])
    out("$self fritz.box:port [BoxInfo|bi]\n\n"
	."Beispiele:\n"
	."$self boxinfo\n"
	."$self 169.254.1.1 bi");
   elseif($data = request('GET','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data,$array)) {
    if($cfg['dbug']/(1<<4)%2)
     dbug($array,'BoxInfos');
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
    out("\nBoxinfo:\n".implode("\n",$array[0]));
   }
   else
    out("Keine Informationen erhalten");
  }
  elseif(ifset($val['gip'])) {				// Get Extern IP
   if(@$cfg['help'])
    out("$self fritz.box:port [GetIP|gip]\n\n"
	."Beispiele:\n"
	."$self getip\n"
	."$self 169.254.1.1 gip");
   elseif($var = getexternalip())
    out("IPv4: $var");
   elseif($var = getsockerror())
    out($var);
   else
    out("Keine Externe IP-Adresse verfügbar");
  }
  elseif(ifset($val['i'])) {				// Info (Intern)
   if(@$cfg['help'])
    out("$self [Info|i]\n\n"
	."Beispiel:\n"
	."$self info\n"
	."$self info php\n"
	."$self i -d");
   elseif($pset < $pmax and preg_match('/^(php)$/',$argv[$pset++]))
    phpinfo();
   elseif($cfg['dbug']) {				// DEBUG: $argv & $cfg ausgeben mit Login-Test
    dbug('$argv');
    $sid = $cfg['sid'] = login();
    dbug('$cfg');
    if($sid)
     logout($sid);
   }
   else
    out("$ver[0]\n".php_uname()."\nPHP ".phpversion()."/".php_sapi_name()."\n\nFile: $script\nSize: ".number_format(filesize($script),0,0,'.')." Bytes\nMD5:  ".md5_file($script)."\nSHA1: ".sha1_file($script));
  }
  elseif(ifset($val['rc'])) {				// ReConnect
   if(@$cfg['help'])
    out("$self fritz.box:port [ReConnect|rc]\n\n"
	."Beispiele:\n"
	."$self reconnect\n"
	."$self 169.254.1.1 rc");
   else
    out(($var = forcetermination()) ? "Ok" : getsockerror(),1);
  } 
  elseif(ifset($val['ss'])) {				// SystemStatus
   if(@$cfg['help'])
    out("$self fritz.box:port [SystemStatus|Status|ss] <supportcode>\n\n"
	."Beispiele:\n"
	."$self systemstatus\n"
	."$self 169.254.1.1 status\n"
	."$self ss \"FRITZ!Box Fon WLAN 7390-B-010203-040506-000000-000000-147902-840522-22574-avm-de\"");
   else
    out(supportcode(($pset < $pmax) ? $argv[$pset] : false));
  }
  elseif(ifset($val['k'])) {				// Konfig
   if(@$cfg['help'] or $pset == $pmax)
    out("$self user:pass@fritz.box:port [Konfig|k] [Funktion] <Datei|Ordner> <Kennwort>

Funktionen:
ExPort          <Datei>  <Kennwort> - Konfig exportieren(1)
ExPort-DeCrypt  <Datei>  <Kennwort> - Konfig entschlüsseln und exportieren(1,3)
ExTrakt         <Ordner> <Kennwort> - Konfig entpackt anzeigen/exportieren(1)
ExTrakt-DeCrypt <Ordner> <Kennwort> - Konfig entpackt entschl./anz./exp.(1,3)
File            [Datei]  <Ordner> - Konfig-Infos aus Datei ausgeben(2)
File            [Ordner] [File]   - Konfig-Ordner in Datei zusammenpacken(2)
File-CalcSum    [Ordner] [File]   - Veränderter Konfig-Ordner Zusammensetzen(2)
File-DeCrypt    [Datei] [Kennwort] <Datei> - Konfig-Daten entschlüsseln(1,3)
ImPort          [Datei|Ordner] <Kennwort>  - Konfig importieren(1)
ImPort-CalcSum  [Datei|Ordner] <Kennwort>  - Veränderte Konfig importieren(1)

(1) Anmeldung mit Logindaten erforderlich / (2) Ohne Fritz!Box nutzbar
(3) Fritz!Box mit OS 5 oder neuer erforderlich / [ ] Pflicht / < > Optional

Beispiele:
$self password@fritz.box konfig export
$self fritz.box konfig extrakt
$self konfig file fritzbox.export
$self fritz.box konfig file-decrypt fb.export geheim fbdc.export -d
$self k fcs Export-Ordner fritzbox.export
$self username:password@fritz.box konfig import \"fb 7170.export\"
$self 169.254.1.1 k ipcs \"FRITZ.Box Fon WLAN 6360 85.04.86_01.01.00_0100.export\"");
   elseif(preg_match('/^(				# 1:Alle
	|i(p|mport)(cs|-calcsum)?			# 2:Import 3:CalcSum
	|e(p|xport)(?:(dc|-decrypt)?)			# 4:Export 5:DeCrypt
	|(et|(?:extra[ck]t))?(?:(dc|-decrypt)?)		# 6:Extrakt 7:DeCrypt
	|(f(?:ile)?)(?:(cs|-calcsum)?|(dc|-decrypt)?)	# 8:File 9:CalcSum 10:DeCrypt
		)$/ix',$argv[$pset++],$mode)) {
    if($cfg['dbug']/(1<<3)%2)				// Debug Parameter
     dbug($mode);
    $mode = array_pad($mode,11,null);
    $file = ($pset < $pmax) ? $argv[$pset++] : false;
    $pass = ($pset < $pmax) ? $argv[$pset++] : false;
    if(($mode[2] or $mode[4] or $mode[6])) {		// Login Optionen
     if($sid = $cfg['sid'] = login()) {
      if($mode[5] or $mode[7]) {			// Kennwort-Entschlüsselung
       if($cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {
        if(!$pass)					// Im DeKode-Modus kein leeres Kennwort zulassen
         $pass = ($cfg['pass']) ? $cfg['pass'] : 'geheim';
       }
       else {
        out("Entschlüsselung wird nicht unterstützt!"); 
        $mode[5] = $mode[7] = false;
       }
      }
      if($mode[4]) {					// Export
       if(is_dir($file)) {				// Im Ordner schreiben
        chdir($file);
        $file = false;
       }
       out(cfgexport($file,$pass,false,$mode[5]));	// File-Export
      }
      elseif($mode[6]) {				// Extrakt
       if($file and !file_exists($file))
        mkdir($file);					// Neues Verzeichniss erstellen
       if(is_dir($file))				// Current-Dir setzen
        chdir($file);
       out(cfgexport($file,$pass,true,$mode[7]));	// Split-Export
      }
      elseif($mode[2] and $file and file_exists($file))	// Import-Konfig
       cfgimport($file,$pass,$mode[3]);
      else
       out("$file kann nicht geöffnet werden!");
      logout($sid);
     }
     else
      out("Login fehlgeschlagen!");
    }
    elseif($mode[8] and !$mode[10] and is_file($file) and $data = file_get_contents($file)) {	// Converter-Modus File -> Dir
     if($pass) {		// Verzeichniss angegeben ?
      if(!file_exists($pass))
       mkdir($pass);		// Neues Verzeichniss erstellen
      if(is_dir($pass))
       chdir($pass);		// Verzeichniss benutzen
     }
     out(($data = cfginfo($data,$pass)) ? $data : "Keine Konfig Export-Datei angegeben");
    }
    elseif($mode[8] and !$mode[10] and is_dir($file) and $pass and (!file_exists($pass) or is_file($pass)))	// Converter-Modus Dir -> File
     out(($data = cfgmake($file,$mode[9],$pass)) ? $data : "Kein Konfig Export-Verzeichnis angegeben");
    elseif($mode[8] and $mode[10] and $pass and is_file($file) and $data = file_get_contents($file)) {		// Kennwörter Entschlüsseln
     if($sid = $cfg['sid'] = login()) {
      if($cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {// Entschlüsselung durchführen
       if($data = konfigdecrypt($data,$pass,$sid)) {
        out(showaccessdata($data));						// Daten als Text Präsentieren
        if($pset < $pmax)							// Optional entschlüsselte Daten speichern
         savedata($argv[$pset++],$data);
       } 
       else
        out("Entschlüsselung fehlgeschlagen, möglicherweise ist das Kennwort falsch!"); 
      }
      else
       out("Entschlüsselung wird nicht unterstützt!"); 
      logout($sid);
     }
     else
      out("Login fehlgeschlagen!");
    }
    else
     out("Parameter-Ressourcen nicht gefunden oder nicht korrekt angegeben\nWeitere Hilfe bekommen Sie mit der -h Option");
   }
   else
    out("Unbekannte Funktionsangabe!");
  }
  elseif(ifset($val['d'])) {				// Dial
   if(@$cfg['help'] or $pset == $pmax)
    out("$self user:pass@fritz.box:port [Dial|d] [Rufnummer] <Telefon>

Telefon:
1-4 -> FON 1-4 | 50 -> ISDN/DECT | 51-58 -> ISDN 1-8 | 60-65 -> DECT 1-6

Beispiele:
$self password@fritz.box dial 0123456789 50
$self username:password@fritz.box dial \"#96*7*\"
$self 169.254.1.1 d -");
   elseif($sid = $cfg['sid'] = login()) {
    out(dial($argv[$pset++],(($pset < $pmax) ? $argv[$pset++] : false)));
    logout($sid);
   }
   else
    out("Login fehlgeschlagen!");
  }
  elseif(ifset($val['sd'])) {				// Supportdaten
   if(@$cfg['help'])
    out("$self user:pass@fritz.box:port [SupportDaten|sd] <filename>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box supportdaten support.txt\n"
	."$self 169.254.1.1 sd");
   elseif($sid = $cfg['sid'] = login()) {
    $text = supportdaten(($pset < $pmax) ? $argv[$pset] : false);
    if($text !== true)
     out($text);
    logout($sid);
   }
   else
    out("Login fehlgeschlagen!");
  }
  elseif(ifset($val['ug'])) {				// UpGrade (Intern)
   if(@$cfg['help'])
    out("$self [UpGrade|ug] <Check>\n\n"
	."Beispiele:\n"
	."$self upgrade check\n"
	."$self ug\n"
	."$self ug c");
   elseif($fbnet = request('GET',"/Projekte;$ver[1].md5",0,0,'mengelke.de',80)) {		// Update-Check
    if(preg_match("/((\d\d)\.(\d\d)\.(\d{4}))\s[\d:]+\s*\((\w+)\s([\d.]+)\)(?:.*?(\w+)\s\*\w+\.$ext(?=\s))?/s",$fbnet,$up)) {
     if(intval($up[4].$up[3].$up[2]) > $ver[8] or floatval($up[6]) > $ver[2]) {
      out("Ein Update ist verfügbar: $up[5] $up[6] vom $up[1]");
      if($pset == $pmax) {
       out("Installiere Update ... ",2);
       if(ifset($up[7]) and $fbnet = @request('GET',"/Projekte;$ver[1].$ext.gz",0,0,'mengelke.de',80)) {
        $chmod = intval(fileperms($script),8);
        if(function_exists('gzdecode') and $var = @gzdecode($fbnet) and md5($var) == $up[7]) {	// Update ab PHP5 (mit gzdecode)
         rename($script,preg_replace('/(\.\w+)?$/','.bak$0',$script));
         savedata($script,$var);
         chmod($script,$chmod);
         $var = true;
        }
        elseif(!function_exists('gzdecode') and $fp = fopen("$script.tmp",'w')) {		// Update ohne gzdecode
         fwrite($fp,$fbnet);
         fclose($fp);
         $var = '';
         if($gz=@gzopen("$script.tmp",'rb')) {							// gzdecode Workaround
          while(!gzeof($gz))
           $var .= gzread($gz,$cfg['sbuf']);
          gzclose($gz);
          unlink("$script.tmp");
         }
         if(md5($var) == $up[7] and rename($script,preg_replace('/(\.\w+)?$/','.bak$0',$script)) and $fp = fopen($script,'w')) {// Script überschreiben
          fwrite($fp,$var);
          fclose($fp);
          chmod($script,$chmod); 
          $var = true;
         }
        }
        out(($var === true) ? "abgeschlossen!" : "fehlgeschlagen!\nBitte installieren Sie es von http://mengelke.de/.dg manuell!");
       }
       else
        out("Automatisches Update ist nicht verfügbar!\nBitte installieren Sie es von http://mengelke.de/.dg manuell!");
      }
     }
     else
      out("Kein neues Update verfügbar!");
    }
    else
     out("Update-Server sagt NEIN!");
   }
    else
     out("Computer sagt NEIN!");
  }
  else
   out("Möglichweise ist ein unerwarterer und unbekannter Fehler aufgetreten :-)");
 }
 elseif($cfg['dbug']) {					// DEBUG: $argv & $cfg ausgeben
  dbug('$argv');
  dbug('$cfg');
 }
 else
  out("$self user:pass@fritz.box:port [mode] <mode-parameter> ... <option>\n".((@$cfg['help']) ? <<<eof
\nModes:
BoxInfo      - Modell, Firmware-Version und MAC-Adresse ausgeben
Dial         - Rufnummer wählen(2)
GetIP        - Aktuelle externe IPv4-Adresse ausgeben(1)
Info         - FB-Tools Version, PHP Version, MD5/SHA1 Checksum 
Konfig       - Einstellungen Ex/Importieren(2,3)
ReConnect    - Neueinwahl ins Internet(1)
SupportDaten - AVM-Supportdaten Speichern(2)
SystemStatus - Modell, Version, Laufzeiten, Neustarts und Status ausgeben(3)
UpGrade      - FB-Tools Updaten oder auf aktuelle Version prüfen(3)

(1) Aktiviertes UPnP erforderlich / (2) Anmeldung mit Logindaten erforderlich
(3) Teilweise ohne Fritz!Box nutzbar / [ ] Pflicht / < > Optional

Optionen:
-b:<Bytes>              - Buffergröße ($cfg[sbuf])
-c:<ansi|html|oem|utf8> - Kodierung der Umlaute ($cfg[char])
-d                      - Debuginfos
-h                      - Hilfe
-o:<Datei>              - Ansi-Ausgabe in Datei
-t:<Sekunden>           - TCP/IP Timeout ($cfg[tout])
-w:<Länge>              - Wortumbruch ($cfg[wrap])

Beispiele:
$self secret@fritz.box supportdaten
$self hans:geheim@fritz.box konfig export
$self 192.168.178.1 systemstatus
eof
: "\nWeitere Hilfe bekommen Sie mit der -h Option"));

 if($cfg['dbug'] and $cfg['error'])			// Fehler bei -d ausgeben
  dbug("Errors:\n".print_r($cfg['error'],true));
}

?>
