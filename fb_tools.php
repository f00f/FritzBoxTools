#!/usr/bin/php
<?php $ver = "fb_tools 0.15 (c) 07.07.2016 by Michael Engelke <http://www.mengelke.de>"; #(charset=iso-8859-1 / tabs=8 / lines=lf)

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
	'upda'	=> 60*60*24*100,	// Auto-Update Periode (Kein Update: 0)
	'wrap'	=> 'auto',		// Manueller Wortumbruch (Kein Umbruch: 0)
	'char'	=> 'auto',		// Zeichenkodierung der Console (auto/ansi/oem/utf8)
	'dbfn'	=> 'debug#.txt',	// Template für Debug-Dateien
	'time'	=> 'Europe/Berlin',	// Zeitzone festlegen
	'drag'	=> 'konfig fcs,-d',	// Drag'n'Drop-Modus
	'help'	=> false,		// Hilfe ausgeben
	'dbug'	=> false,		// Debuginfos ausgeben
	'oput'	=> false,		// Ausgaben speichern
#	'error' => array(),		// Fehlerlogs
#	'preset'=> array(),		// Leere Benutzerkonfiguration
#	'boxinfo'=>array(),		// Leere Boxinfo Daten
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
if(!function_exists('utf8_encode')) {			// http://php.net/utf8_encode (Fallback auf 7bit)
 function utf8_encode($str) {
  return preg_replace('/[\x80-\xff]+/','?',$str);
 }
 $cfg['utf8'] = false;
}
if(!function_exists('utf8_decode')) {			// http://php.net/utf8_decode (Fallback auf 7bit)
 function utf8_decode($str) {
  return preg_replace('/[\x80-\xff]+/','?',$str);
 }
 $cfg['utf8'] = false;
}
if(!function_exists('file_put_contents')) {		// http://php.net/file_put_contents
 function file_put_contents($file,$data,$opt=0) {	// $opt ist nicht vollständig implemmentiert
  if($fp = fopen($file,($opt/(1<<3)%2) ? 'a' : 'w')) {	// FILE_APPEND -> 8
   if(is_array($data))
    $data = implode('',$data);
   if($opt/(1<<1)%2) {	// LOCK_EX -> 2
    if(flock($fp,2)) {	// flock LOCK_EX
     fputs($fp,$data);
     flock($fp,3);	// flock LOCK_UN
    }
    else {
     fclose($fp);
     return errmsg("$file konnte nicht zum exklusiven Schreiben geöffnet werden",__FUNCTION__);
    }
   }
   else
    fputs($fp,$data);
   fclose($fp);
   $fp = strlen($data);
  }
  else
   errmsg("$file konnte nicht zum Schreiben geöffnet werden",__FUNCTION__);
  return $fp;
 }
}
function crc_32($str,$file=false) {			// Berechnet die CRC32 Checksume von einen String (Optional von einer Datei)
 return str_pad(sprintf('%X',crc32(($file) ? file_get_contents($str) : $str)),8,0,STR_PAD_LEFT);
}
function ifset(&$x,$y=false) {				// Variabeln prüfen
 return (isset($x) and ($x or $x != '')) ? (($y and is_string($x) and $y{0} == '/' and preg_match($y,$x,$z)) ? $z : (($y) ? $x == $y : !$y)) : false;
}
function out($str,$mode=0) {				// Textconvertierung vor der ausgabe ($Mode: Bit0 -> echo / Bit1 -> autolf / Bit2 -> debug)
 global $cfg;
 if($str) {
  if(is_array($str))
   $str = print_r($str,true);
  if(!($mode/(1<<1)%2) and preg_match('/\S$/D',$str))	// AutoLF
   $str .= "\n";
  if($mode/(1<<2)%2)					// Unnötige Whitespaces im Debug-Modus löschen
   $str = preg_replace('/(?<=\n\n|\r\n\r\n)\s+$/','',$str);
  if($cfg['oput'] and !($mode/(1<<2)%2))		// Ausgabe speichern
   file_put_contents($cfg['oput'],$str,8);
  if((int)$cfg['wrap'] and $cfg['char'] != '7bit')	// Wortumbruch
   $str = wordwrap($str,$cfg['wrap']-1,"\n",true);
  if(preg_match('/^(dos|oem|(codepage|cp)?(437|850))$/',$cfg['char']))
   $str = str_replace(array('ä','ö','ü','ß','Ä','Ö','Ü','§',"\n"),array(chr(132),chr(148),chr(129),chr(225),chr(142),chr(153),chr(154),chr(21),"\r\n"),$str);
  elseif($cfg['char'] == 'utf8')
   $str = utf8_encode($str);
  elseif($cfg['char'] == 'html')
   $str = str_replace(array('&','<','>','"',"'",'ä','ö','ü','ß','Ä','Ö','Ü'),
    array('&amp;','&lt;','&gt;','&quot;',"&#39;",'&auml;','&ouml;','&uuml;','&szlig;','&Auml;','&Ouml;','&Uuml;'),$str);
  else /* if($cfg['char'] == '7bit') */ {
   $str = str_replace(array('ä','ö','ü','ß','Ä','Ö','Ü','§'),array('ae','oe','ue','sz','Ae','Oe','Ue','SS'),$str);
   if((int)$cfg['wrap'] and $cfg['char'] == '7bit')	// Wortumbruch
    $str = wordwrap($str,$cfg['wrap'],"\n",true);
  }
 }
 return ($mode/(1<<0)%2) ? $str : print $str;
}
function dbug($str,$file=false) {			// Debug-Daten ausgeben/speichern
 global $cfg;
 $time = ($cfg['dbug']/(1<<2)%2) ? number_format(array_sum(explode(' ',microtime()))-$cfg['time'],2,',','.').' ' : '';
 if($cfg['dbug']/(1<<1)%2 and $cfg['dbfn'] and $file)	// Debug: Array in separate Datei sichern
  if(strpos($file,'#') and is_array($str))
   foreach($str as $key => $var)			// Debug: Array in mehrere separaten Dateien sichern
    file_put_contents(str_replace('#',"-".str_replace('#',$key,$file),$cfg['dbfn']),$time.((is_array($var)) ? print_r($var,true) : $var),8);
  else
   file_put_contents(str_replace('#',"-$file",$cfg['dbfn']),$time.((is_array($str)) ? print_r($str,true) : $str),8);	// Alles in EINE Datei Sichern
 else {
  if(is_string($str)) {
   if(preg_match('/^\$(\w+)$/',$str,$var) and isset($GLOBALS[$var[1]]))	// GLOBALS Variable ausgeben
    $str = "$str => ".(is_array($GLOBALS[$var[1]]) ? print_r($GLOBALS[$var[1]],true) : $GLOBALS[$var[1]]);
   elseif(preg_match('/\S$/D',$str))			// AutoLF
    $str .= "\n";
  }
  elseif(is_array($str))
   $str = print_r($str,true);
  if($cfg['dbug']/(1<<1)%2 and $cfg['dbfn'])		// Debug: Ausgabe/Speichern
   file_put_contents(str_replace('#','',$cfg['dbfn']),$time.$str,8);
  else
   out($time.$str,4);
 }
}
function errmsg($msg,$name=false) {			// Fehlermeldung(en) Sichern
 global $cfg;
 if(!$name)			// Functionname angegeben?
  $name = 'main';
 if($msg) {			// Fehlermeldung speichern
  $cfg['error'][$name][] = trim($msg);
  return false;
 }
 else				// Fehlermeldung abrufen
  while(isset($cfg['error'][$name]) and is_array($cfg['error'][$name]))	// Fehlermeldung vorhanden?
   if($val = end($cfg['error'][$name]) and preg_match('/^\w+$/',$val))	// Möglicher Rekusive Fehlermeldung?
    $name = $val;		// Nächste Fehlermeldung suchen
   else
    return $val;		// Fehlermeldung ausgeben
 return false;			// Funktion fehlgeschlagen
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
 if($mode = preg_match('/^(\w+)(?:-(.+))?/',$method,$var)) {
  $method = strtoupper($var[1]);
  $mode = (isset($var[2])) ? $var[2] : (($var[1] == strtolower($var[1])) ? 'array' : false);	// Result-Modus festlegen
 }
 if($cfg['dbug']/(1<<5)%2)
  dbug("$host:$port ");
 if($fp = @fsockopen($host,$port,$errnr,$errstr,$cfg['tout'])) {	// Verbindung aufbauen
  stream_set_timeout($fp,$cfg['tout']);			// Timeout setzen
//  stream_set_blocking($fp,0);
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
   dbug("$method $page".(($cfg['dbug']/(1<<7)%2) ? "$head$body\n\n" : ''),'RequestPut');
  fputs($fp,"$method $page $head$body");		// Request Absenden
  if($mode == 'putonly')				// Nur Upload durchführen
   return fclose($fp);
  $rp = "";						// Antwort vorbereiten
  if(preg_match('/(?:save|down(?:load)?):(.*)/',$mode,$file)) {	// Download -> Datei
   while(!feof($fp)) {
    $rp .= fread($fp,$cfg['sbuf']);
    if($pos = strpos($rp,"\r\n\r\n")) {			// Header/Content trennen
     $header = substr($rp,0,$pos);
     $rp = substr($rp,$pos+4);
     $file[1] = preg_replace('/(?<=\/)$/',($file[1]
	and preg_match('/^Content-Disposition:\s*(?:attachment;\s*)?filename=(["\']?)(.*?)\1\s*$/mi',$header,$var))
	? $var[2] : 'file.bin',$file[1]);
     if($cfg['dbug']/(1<<0)%2)
      dbug("Downloade '$file[1]'");
     if($sp = fopen($file[1],'w')) {
      fputs($sp,$rp);
      while(!feof($fp))
       fputs($sp,fread($fp,$cfg['sbuf']));
      fclose($sp);
      $rp = $header;
     }
     else
      return errmsg("$file[1] kann nicht zum Schreiben geöffnet werden",__FUNCTION__);
    }
   }
  }
  else
   while(!feof($fp))
    $rp .= fread($fp,$cfg['sbuf']);
  fclose($fp);
  $fp = $rp;
  if($cfg['dbug']/(1<<6)%2)				// Debug Response
   dbug((($cfg['dbug']/(1<<7)%2) ? $rp : preg_replace('/\n.*$/s','',$rp))."\n\n",'RequestGet');
  if($mode != 'raw' and preg_match('/^(http[^\r\n]+)(.*?)\r\n\r\n(.*)$/is',$rp,$array)) {	// Header vom Body trennen
   if($mode == 'array') {
    $fp = array($array[1]);
    if(count($array) > 0 and preg_match_all('/^([^\s:]+):\s*(.*?)\s*$/m',$array[2],$array[0]))
     foreach($array[0][2] as $key => $var)
      $fp[preg_replace_callback('/(?<=^|\W)\w/','prcb_stu',$array[0][1][$key])] = $var;
    $fp[1] = $array[3];
   }
   else
    $fp = $array[3];
  }
 }
 else
  errmsg("$host:$port - Fehler $errnr: $errstr",__FUNCTION__);
 return $fp;
}
function prcb_stu($x) {					// PREG_Replace_Callback Uppercase
 return strtoupper($x[0]);
}
function response($xml,$pass,$page=false) {		// Login-Response berechnen
 if(preg_match('!<Challenge>(\w+)</Challenge>!',$xml,$var)) {
  $hash = "response=$var[1]-".md5(preg_replace('!.!',"\$0\x00","$var[1]-$pass"));
  if($page and $GLOBALS['cfg']['fiwa'] == 100)
   $GLOBALS['cfg']['fiwa'] = (substr($page,-4) == '.lua') ? '529' : '474';
  return $hash;
 }
 else
  return errmsg('Keine Challenge erhalten',__FUNCTION__);
}
function login($pass=false,$user=false) {		// In der Fritz!Box einloggen
 global $cfg;
 foreach(array('user','pass') as $var)			// User & Pass setzen
  if(!$$var)
   $$var = $GLOBALS['cfg'][$var];
 if($cfg['dbug']/(1<<0)%2)
  dbug("Login ".(($user) ? "$user@" : "")."$cfg[host]");
 if($data = request('GET-array','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data[1],$array)) {	// BoxInfos holen
  if($cfg['dbug']/(1<<4)%2)
   dbug($array);
  $cfg['boxinfo'] = array_combine($array[1],$array[2]);
  $cfg['boxinfo']['Time'] = strtotime($data['Date']);
  if(preg_match('/^\d+\.0*(\d+?)\.(\d+)$/',$cfg['boxinfo']['Version'],$var))	// Firmware-Version sichern
   $cfg['fiwa'] = $var[1].$var[2];
 }
 else
  errmsg('request',__FUNCTION__);
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
   else
    errmsg("Anmeldung fehlgeschlagen",__FUNCTION__);
  }
 }
 else
  errmsg('request',__FUNCTION__);
 return $cfg['sid'] = $sid;				// SID zurückgeben
}
function logout($sid) {					// Aus der Fritz!Box ausloggen
 if($GLOBALS['cfg']['dbug']/(1<<0)%2)
  dbug("Logout ".$GLOBALS['cfg']['host']);
 if(is_string($sid) and $sid)				// Ausloggen
  request('GET',(($GLOBALS['cfg']['fiwa'] < 529) ? "/cgi-bin/webcm" : "/login_sid.lua"),"security:command/logout=1&logout=1&sid=$sid");
}
function supportcode($str = false) {			// Supportcode aufschlüsseln
 return ($str or $str = request('GET','/cgi-bin/system_status')) ? ((preg_match('!
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
  :	"Unbekannt: $str") : errmsg('request',__FUNCTION__);
}
function upnprequest($page,$ns,$rq,$exp=false) {	// UPnP Request durchführen
 return ($rp = request(array(
	'method' => 'POST',
	'page' => $page,
	'body' => utf8_encode("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
	."<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" s:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">\n"
	."<s:Body><u:$rq xmlns:u=$ns /></s:Body>\n</s:Envelope>"),
	'head' => array_merge($GLOBALS['cfg']['head'],array('content-type' => 'text/xml; charset="utf-8"', 'soapaction' =>  "\"$ns#$rq\"")),
	'port' => $GLOBALS['cfg']['upnp'])))
  ? (($exp) ? ((preg_match("/<$exp>(.*?)<\/$exp>/",$rp,$var)) ? $var[1] : errmsg('Kein Erwartetes Ergebnis erhalten',__FUNCTION__)) : $rp)
  : errmsg('request',__FUNCTION__);
}
function getupnppath($urn) {				// Helper für UPnP-Requests
 return ($rp = request(array('method' => 'GET', 'page' => '/igddesc.xml', 'port' => $GLOBALS['cfg']['upnp']))
	and preg_match("!<(service)>.*?<(serviceType)>(urn:[^<>]*".$urn."[^<>]*)</\\2>.*?<(controlURL)>(/[^<>]+)</\\4>.*?</\\1>!s",$rp,$var))
	? array($var[3],$var[5]) : errmsg('request',__FUNCTION__);
}
function getexternalip() {				// Externe IPv4-Adresse über UPnP ermitteln
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'GetExternalIPAddress','NewExternalIPAddress')) ? $var
  : errmsg('request',__FUNCTION__);
}
function forcetermination() {				// Internetverbindungen über UPnP neu aufbauen
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'ForceTermination','NewExternalIPAddress')) ? $var
  : errmsg('request',__FUNCTION__);
}
function saverpdata($file,$data,$name) {		// HTTP-Downloads in Datei speichern
 $file = preg_replace('/[<>\[\]:\/\\\\"*?|]/','_',($file) ? $file : ((@preg_match('/filename="(.*)"/',$data['Content-Disposition'],$var)) ? $var[1] : $name));
 $cfg['file'] = $file;
 return file_put_contents($file,$data[1]);
}
function supportdaten($file,$sid=false) {		// Supportdaten erstellen
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $array = array();
 if($sid and $sid !== true)
  $array['sid'] = $sid;
 $array['SupportData'] = '';
 return ($data = request('POST-save:'.(($file) ? $file : './'),'/cgi-bin/firmwarecfg',$array)) ? true : errmsg('request',__FUNCTION__);
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
function cfgexport($file,$pass=false,$mode=false,$decrypt=false,$sid=false) {	// Konfiguration Exportieren (Wird bald überarbeitet)
 global $cfg;
 $body = array('ImportExportPassword' => $pass, 'ConfigExport' => false);
 if(!$sid) {
  $sid = $cfg['sid'];
  $body = array_merge(array('sid' => $cfg['sid']),$body);
 }
 if($mode == 'save' and !$decrypt)
  return (request('POST-save:'.(($file) ? $file : './'),'/cgi-bin/firmwarecfg',$body)) ? 'Konfig erfolgreich exportiert' : errmsg('request',__FUNCTION__);
 elseif($data = request('POST-array','/cgi-bin/firmwarecfg',$body)) {	// Konfig aus der Fritz!Box holen
  $txt = '';
  if($decrypt and $pass and $cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {	// Kennwörter entschlüsseln
   $data[1] = konfigdecrypt($data[1],$pass,$sid);
   $txt = showaccessdata($data[1]);
  }
  return ($mode == 'info')	? cfginfo($data[1],$file,$txt).(($file) ? "" : $txt)	// Konfig anzeigen
		: (($mode == 'save') ? ((saverpdata($file,$data,"fritzbox.export"))	// Konfig speichern
		? $txt : errmsg("$cfg[file] kann nicht geschrieben werden",__FUNCTION__)) : (($mode == 'array') ? $data : $data[1]));
 }
 else
  return errmsg('request',__FUNCTION__);
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
    $array[0][$key] = $array[5][$key]."\0".pack('H*',preg_replace('/[^\da-f]+/i',"",$array[6][$key]));
  }
 }
 return ($array and preg_match('/(?<=^\*{4} END OF EXPORT )[A-Z\d]{8}(?= \*{4}\s*$)/m',$data,$key,PREG_OFFSET_CAPTURE))
	? array($key[0][0],$var = crc_32(join('',$array[0])),substr_replace($data,$var,$key[0][1],8)) : errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function cfgimport($file,$pass=false,$mode=false,$sid=false) {	// Konfiguration importieren (Wird bald überarbeitet)
 if($file and (is_file($file) and ($data = file_get_contents($file)) or is_dir($file) and ($data = cfgmake($file)))
	or !$file and $data = $mode and substr($mode,0,4) == '****') {
  if($mode and $var = cfgcalcsum($data))
   $data = $var[2];
  if($GLOBALS['cfg']['dbug']/(1<<0)%2)
   dbug("Upload Konfig-File an ".$GLOBALS['cfg']['host']);
  $body = array('ImportExportPassword' => $pass,
	'ConfigImportFile' => array('filename' => $file, 'Content-Type' => 'application/octet-stream', '' => $data),
	'apply' => false);
  if(!$sid and $GLOBALS['cfg']['sid'] !== true)
   $body = array_merge(array('sid' => $GLOBALS['cfg']['sid']),$body);
  return request('POST','/cgi-bin/firmwarecfg',$body);
 }
 else
  return errmsg('Import-Datei/Ordner nicht gefunden',__FUNCTION__);
}
function cfginfo($data,$mode,$text=false) {		// Konfiguration in Einzeldateien sichern
 if(preg_match_all('/^(?:
	\*{4}\s(.*?)\sCONFIGURATION\sEXPORT|(\w+=\S+))\s*$	# 1 Fritzbox-Modell, 2 Variablen
	
	|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?\r?\n(.*?)\r?\n	# 3 Typ, 4 File, 5 Data
	^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$/msx',$data,$array) and $array[1][0] and $crc = cfgcalcsum($data)) {
  $list = $val = $vars = array();
  $mstr = $mlen = array(0,0);
  if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)			// Debugdaten Speichern
   dbug($array,'CfgInfo-#');
  foreach($array[3] as $key => $var)			// Config-Dateien aufteilen
   if($var) {
    if($array[3][$key] == 'CFG') {
     $bin = str_replace(array("\r","\\\\"),array("","\\"),$array[5][$key]);
     if(!isset($vars['Date']) and preg_match('/^\s\*\s([\s:\w]+)$/m',$bin,$var))
      $vars['Date'] = strtotime($var[1]);
    }
    else
     $bin = pack('H*',preg_replace('/[^\da-f]+/i',"",$array[5][$key]));
    $list[] = array($array[3][$key],$array[4][$key],number_format(strlen($bin),0,",","."));
    if($mode)
     file_put_contents($array[4][$key],$bin);
    unset($array[2][$key]);
   }
   elseif($var = ifset($array[2][$key],'/^(\w+)=(.*)$/'))
    $vars[$var[1]] = $var[2];
   else
    unset($array[2][$key]);
  $file = "pattern.txt";				// Konfig-Schablone sichern
  $data = preg_replace('/^(\*{4}\s(?:CRYPTED)?(?:CFG|BIN)FILE:\S+\s*?\r?\n).*?\r?\n(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?)$/msx','$1$2',$data);
  $list[] = array("TXT",$file,number_format(strlen($data),0,",","."));
  if($mode)
   file_put_contents($file,$data);
  if($text) {						// Zugangsdaten sichern
   $file = "zugangsdaten.txt";
   $list[] = array("TXT",$file,number_format(strlen($text),0,",","."));
   if($mode)
    file_put_contents($file,$text);
  }
  foreach($list as $key => $var) {			// Maximale Längen ermitteln
   $c = ($key < count($list)/2) ? 0 : 1;
   $mstr[$c] = max($mstr[$c],strlen($var[1]));
   $mlen[$c] = max($mlen[$c],strlen($var[2]));
  }
  for($a=0;$a<count($list);$a++)			// Liste zusammenstellen
   if(@$var=$list[(($a-$a%2)/2)+floor(count($list)%2+count($list)/2)*($a%2)])
    $val[$a-$a%2] = ((isset($val[$a-$a%2])) ? $val[$a-$a%2] : '').$var[0].": ".str_pad($var[1],$mstr[$a%2]," ")." ".str_pad($var[2],$mlen[$a%2]," ",STR_PAD_LEFT)." Bytes   ";
  $list = "\nModell:   {$array[1][0]}\n";
  if(ifset($vars['Date']))
   $list .= "Datum:    ".date('d.m.Y H:i:s',$vars['Date'])."\n";
  if(ifset($vars['FirmwareVersion']))
   $list .= "Firmware: $vars[FirmwareVersion]\n";
  return $list."Checksum: $crc[0] (".(($crc[0] == $crc[1]) ? "OK" : "Inkorrekt! - Korrekt: $crc[1]").")\n\n"
	.implode("\n",$val)."\n";
 }
 else
  return errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function cfgmake($dir,$mode=false,$file=false) {	// Konfiguration wieder zusammensetzen
 if($data = file_exists("$dir/pattern.txt") and file_get_contents("$dir/pattern.txt") and preg_match('/^\*{4}\s+FRITZ!/m',$data,$array)) {
  $GLOBALS['val'] = $dir;
  $data = preg_replace_callback('/(^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?(\r?\n))(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$)/m','prcb_cfgmake',$data);
  if(preg_match('/^\*{4}\s(.*?)\sCONFIGURATION\sEXPORT.*?FirmwareVersion=(\S+)/s',$data,$array) and $crc = cfgcalcsum($data)) {
   $val = "Modell:   $array[1]\nFirmware: $array[2]\nChecksum: $crc[0] ";
   $val .= (($crc[0] == $crc[1]) ? "(OK)" : "Inkorrekt! - Korrekt: $crc[1]")."\n";
   $data = ($mode) ? $crc[2] : $data;
   file_put_contents($file,$data);
   return ($file) ? $val : $data;
  }
 }
 return errmsg("Kein Konfig-Ordner - $dir/pattern.txt nicht gefunden",__FUNCTION__);
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
  $dupe = array(array(1,4,'/^(.*?)$/'),array(15,5,'/(?<=^|,\s)(.*?)(?=,\s|$)/'));
  $pregimport = array(
   'de' => array('Internetzugangsdaten' => array(1,0,'/^Benutzer:\s(.*?)(?:,\sAnbieter:\s.*?)?$/'),'Dynamic DNS' => array(2,1,'/(?<=Domainname:\s|Benutzername:\s)(.*?)(?=,\s|$)/'),
    'PushService' => array(1,3,'/^E-Mail-Empfänger:\s(.*?)$/'),'MyFRITZ!' => $dupe[0],'FRITZ!Box-Benutzer' => $dupe[1]),
   'en' => array('Internet Account Information' => array(1,0,'/^User:\s(.*?)(?:,\sProvider:\s.*?)?$/'),'Dynamic DNS' => array(2,1,'/(?<=Domain\sname:\s|user\sname:\s)(.*?)(?=,\s|$)/'),
    'Push service' => array(1,3,'/^e-mail\srecipient:\s(.*?)$/'),'MyFRITZ!' => $dupe[0],'FRITZ!Box Users' => $dupe[1]),
   'es' => array('Datos de acceso a Internet' => array(1,0,'/^Usuario:\s(.*?)(?:,\sProvider:\s.*?)?$/'),'DNS dinámico' => array(2,1,'/(?<=Nombre\sdel\sdominio:\s|nombre\sdel\susuario:\s)(.*?)(?=,\s|$)/'),
    'Push Service' => array(1,3,'/^Destinatario\sde\scorreo:\s(.*?)$/'),'MyFRITZ!' => $dupe[0],'Usuarios de FRITZ!Box' => $dupe[1]),
   'fr' => array('Données d\'accès à Internet' => array(1,0,'/^Utilisateur[\xa0\s]?:[\xa0\s](.*?)(?:,\sProvider:\s.*?)?$/'),'DNS dynamique' => array(2,1,'/(?<=Nom\sde\sdomaine[\xa0\s]:[\xa0\s]|nom\sd\'utilisateur[\xa0\s]:[\xa0\s])(.*?)(?=,\s|$)/'),
    'Service push' => array(1,3,'/^Destinataire\sdu\scourrier\sélectronique[\xa0\s]?:[\xa0\s](.*?)$/'),'MyFRITZ!' => $dupe[0],'Utilisateur de FRITZ!Box' => $dupe[1]),
   'it' => array('Dati di accesso a Internet' => array(1,0,'/^Utente:\s(.*?)(?:,\sProvider:\s.*?)?$/'),'Dynamic DNS' => array(2,1,'/(?<=Nome\sdi\sdominio:\s|nome\sutente:\s)(.*?)(?=,\s|$)/'),
    'Servizio Push' => array(1,3,'/^Destinatario\se-mail:\s(.*?)$/'),'MyFRITZ!' => $dupe[0],'Utenti FRITZ!Box' => $dupe[1]),
   'pl' => array('Dane dost?powe do internetu' => array(1,0,'/^U\?ytkownik:\s(.*?)(?:,\sProvider:\s.*?)?$/'),'Dynamic DNS' => array(2,1,'/(?<=Nazwa\sdomeny:\s|nazwa\su\?ytkownika:\s)(.*?)(?=,\s|$)/'),
    'Push Service' => array(1,3,'/^Odbiorca\se-maila:\s(.*?)$/'),'MyFRITZ!' => $dupe[0],'U?ytkownicy FRITZ!Box' =>  $dupe[1]),
  );
  $lang = (ifset($cfg['boxinfo']['Lang']) and ifset($pregimport[$cfg['boxinfo']['Lang']])) ? $cfg['boxinfo']['Lang'] : 'de';
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
    if($getdata = utf8_decode(($cfg['fiwa'] < 650) ? request('GET',"/system/cfgtakeover_edit.lua?sid=$sid&cfg_ok=1") : request('POST',"/data.lua","xhr=1&sid=$sid&lang=de&no_sidrenew=&page=cfgtakeover_edit"))) {
     if(preg_match_all('/^\s*\["add\d+_text"\]\s*=\s*"([^"]+)",\s*$.*?^\s*\["gui_text"\]\s*=\s*"([^"]+)",\s*$/sm',$getdata,$match))
      $match[2] = array_flip($match[2]);
     elseif(preg_match_all('/<label for="uiCheckcfgtakeover\d+">(.*?)\s*<\/label>\s*<span class="addtext">(.*?)\s*<br>\s*<\/span>/',$getdata,$match))
      $match = array(1 => $match[2], 2 => array_flip($match[1]));
     if($cfg['dbug']/(1<<4)%2)
      dbug($match,'KonfigDeCrypt-Match');
     if($match) {					// Decodierte Kennwörter gefunden
      foreach($pregimport[$lang] as $key => $var)
       if(isset($match[2][$key]) and preg_match_all($var[2],$match[1][$match[2][$key]],$array))
        foreach($array[1] as $k => $v)
         if(isset($buffer[$var[1] + $k])) {		// Kennwort sichern
          $plain[$buffer[$var[1] + $k]] = str_replace('"','\\\\"',html_entity_decode($v));
          unset($buffer[$var[1] + $k]);
         }
     }
     else
      return errmsg('Keine Entschlüsselte Daten gefunden',__FUNCTION__);
    }
    else
     return errmsg('Keine Daten erhalten',__FUNCTION__);
   }
   else
    return errmsg('Entschlüsselungsversuch wurde nicht akzeptiert',__FUNCTION__);
   if($cfg['dbug']/(1<<0)%2)
    dbug((count($list)) ? floor(count($plain)/(count($list)+count($plain))*100)."% entschlüsselt..." : "100% - Ersetze ".count($plain)." entschlüsselte Einträge...");
  }
  return str_replace(array_keys($plain),array_values($plain),$data);
 }
 return errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function konfig2array($data) {				// FRITZ!Box-Konfig -> Array
 $config = array();
 if($data{0} == '*' and preg_match_all('/^(?:\*{4}\s(.*?)\sCONFIGURATION\sEXPORT|(\w+)=(\S+))\s*$
	|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?\r?\n(.*?)\r?\n^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$/msx',$data,$array)) {
  if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)			// Debugdaten Speichern
   dbug($array,'Konfig2Array-#');
  foreach($array[4] as $key => $var)
   if(ifset($array[1][$key]))				// Routername
    $config['Name'] = $array[1][$key];
   elseif(ifset($array[2][$key]))			// Variablen
    $config[$array[2][$key]] = $array[3][$key];
   elseif(ifset($array[4][$key],'BIN'))			// BinData
    $config[$array[5][$key]] = pack('H*',preg_replace('/[^\da-f]+/i','',$array[6][$key]));
   elseif(ifset($array[4][$key],'CFG') and preg_match_all('/^(\w+)\s(\{\s*$.*?^\})\s*$/smx',
	str_replace(array("\r","\\\\"),array("","\\"),$array[6][$key]),$match))	// CfgData
    foreach($match[1] as $k => $v)
     $config/*[$array[5][$key]]*/[$v] = konfig2array($match[2][$k]);
  if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)			// Debugdaten Speichern
   dbug($config,'Konfig2Array');
 }
 elseif($data{0} == '{' and preg_match_all('/\{\s*?$.*?^\}/msx',$data,$array)) {
  if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)			// Debugdaten Speichern
   dbug($array,'Konfig2Array-Multi-#');
  if(count($array[0]) > 1)				// Ein oder Multi-Array
   foreach($array[0] as $var)				// Weitere Matches auf selber Ebene
    $config[] = konfig2array($var);
  elseif(preg_match_all('/^\s{8}(?:(\w+)\s(?:=\s(?:([^\s"]+)|(".*?(?<!\\\\)"(?:,\s*)?));|(\{\s*$.*?^\s{8}\}))\s*$)$/msx',$data,$match)) {
   if(@$GLOBALS['cfg']['dbug']/(1<<4)%2)		// Debugdaten Speichern
    dbug($match,'Konfig2Array-Sub-#');
   foreach($match[1] as $key => $var)			// Array durch arbeiten
    if(ifset($match[2][$key]))				// Einfache Werte
     $config[$var] = ($match[2][$key] == 'yes') ? true  : (($match[2][$key] == 'no') ? false : (($match[2][$key] == (int)$match[2][$key]) ? (int)($match[2][$key]) : $match[2][$key]));
    elseif(ifset($match[3][$key]) and preg_match_all('/"(.*?)(?<!\\\\)"/',$match[3][$key],$val))	// String(s)
     $config[$var] = str_replace('\"','"',(count($val[1]) > 1) ? $val[1] : $val[1][0]);
    elseif(ifset($match[4][$key]))			// Verschachteltes Array
     $config[$var] = konfig2array(preg_replace('/^\s{8}/m','',$match[4][$key]));
  }
 }
 else
  return errmsg('Keine Konfig-Datei',__FUNCTION__);
 return $config;
}
function showaccessdata($data) {			// Die Kronjuwelen aus Konfig-Daten heraussuchen
 $text = '';
 $config = array();
 if($konfig = konfig2array($data)) {		// Konfig als Array umwandeln
  $access = array(
   'Mobile-Stick' => array(&$konfig['ar7cfg']['serialcfg'],'=number,provider,username,passwd'),
   'DSL' => array(&$konfig['ar7cfg']['targets'],'-name,>local>username,>local>passwd'),
   'IPv6' => array(&$konfig['ipv6']['sixxs'],'=ticserver,username,passwd,tunnelid'),
   'DynamicDNS' => array(&$konfig['ddns']['accounts'],'=domain,username,passwd'),
   'MyFRITZ!' => array(&$konfig['jasonii'],'=user_email,user_password,box_id,box_id_passphrase,dyn_dns_name'),
   'FRITZ!Box-Oberfläche' => array(&$konfig['webui'],'=username,password'),
   'Fernwartung' => array(&$konfig['websrv']['users'],'=username,passwd'),
   'TR-069-Fernkonfiguration' => array(&$konfig['tr069cfg'],'=url,username,password'),
   'Telekom-Mediencenter' => array(&$konfig['t_media'],'=refreshtoken,accesstoken'),
   'Google-Play-Music' => array(&$konfig['gpm'],'=emailaddress,password,partition,servername'),
   'Onlinespeicher' => array(&$konfig['webdavclient'],'=host_url,username,password'),
   'WLAN' => array(&$konfig['wlancfg'],'/^((guest_)?(ssid(_scnd)?|pskvalue)|(sta_)?key_value\d|wps_pin)$/i'),
   'Push-Dienst' => array(&$konfig['emailnotify'],'=From,To,SMTPServer,accountname,passwd','+To,arg0'),
   'FRITZ!Box-Benutzer' => array(&$konfig['boxusers']['users'],'-name,email,passwd,password'),
   'InternetTelefonie' => array(&$konfig['voipcfg'],'_name,username,authname,passwd,registrar,stunserver,stunserverport'),
   'IP-Telefon' => array(&$konfig['voipcfg']['extensions'],'-extension_number,username,authname,passwd,clientid'),
   'Online-Telefonbuch' => array(&$konfig['voipcfg']['onlinetel'],'-pbname,url,serviceid,username,passwd,refreshtoken,accesstoken'),
   'Virtual-Privat-Network' => array(&$konfig['vpncfg']['connections'],'-name,>localid<fqdn,>remoteid<fqdn,>localid<user_fqdn,>remoteid<user_fqdn,key,>xauth>username,>xauth>passwd'),
  );
  foreach($access as $key => $var)		// Accessliste durcharbeiten
   if(ifset($var[0])) {
    if($var[1]{0} == '/') {			// Reguläre Ausdrücke verwenden
     foreach($var[0] as $k => $v)
      if(preg_match($var[1],$k) and $var[0][$k])// Schlüssel Suchen und Prüfen
       $config[$key][$k] = $var[0][$k];
    }
    elseif($var[1]{0} == '=') {			// Normal abfragen
     $keys = explode(',',substr($var[1],1));
     foreach($keys as $k)
      if(ifset($var[0][$k]))			// Schlüssel Testen
       $config[$key][$k] = $var[0][$k];
    }
    if(preg_match('/^([-+_])(.+)$/',$var[(isset($var[2])) ? 2 : 1],$keys)) {	// Eine Schlüssel-Ebene überspringen
     if($keys[1] == '-' and count(array_filter(array_keys($var[0]),'is_string')) > 0)
      $var[0] = array($var[0]);
     $keys[3] = explode(',',$keys[2]);
     foreach($var[0] as $k => $v)
      if((preg_match('/\d+\s*$/',$k,$val) or $keys[1] == '+') and is_array($v)) {	// Neue Ebene gefunden
       $name = ($val) ? false : $k;
       foreach($keys[3] as $val)
        if($val{0} == '>' and preg_match('/(\w+)([<>])(\w+)/',$val,$va1) and ifset($var[0][$k][$va1[1]][$va1[3]]))	// Mit Reguläre Ausdrücke noch eine Ebene überspringen
         if($name === false)
          $name = (string)$var[0][$k][$va1[1]][$va1[3]];
         else
          $config[$key][$name][(($va1[2] == '<') ? $va1[1] : $va1[3])] = $var[0][$k][$va1[1]][$va1[3]];
        elseif(ifset($var[0][$k][$val]))		// Auf der neuen Ebene Prüfen
         if($name === false)
          $name = (string)$var[0][$k][$val];
         else
          $config[$key][$name][$val] = $var[0][$k][$val];
      }
    }
   }
  if($GLOBALS['cfg']['dbug']/(1<<4)%2)
   dbug($config,'ShowAccessData');
  foreach($config as $key => $var) {			// Array in Text Umwandeln
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
 }
 return $text;
}

# Eigentlicher Programmstart

if(isset($argv) and count($argv) > 0 and preg_match('/^(\w+) ([\d.]+) \(c\) (\d\d)\.(\d\d)\.(\d{4}) by ([\w ]+?) <([.:\/\w]+)>$/',$ver,$ver)) { ## CLI-Modus ##
 $ver[2] = floatval($ver[2]);				// fb_tools Version
 $ver[] = intval($ver[5].$ver[4].$ver[3]);		// fb_tools Datum
 $uplink = array("mengelke.de","/Projekte;$ver[1].");	// Update-Link
 if(!$script = realpath($argv[0]))			// Pfad zum Scipt anlegen
  $script = realpath($argv[0].".bat");			// Workaround für den Windows-Sonderfall
 $self = basename($script);
 $ext = preg_replace('/\W+/','',pathinfo($script,PATHINFO_EXTENSION)); // Extension für Unix/Win32 unterscheidung
 $cfg['head'] = array('Useragent' => "$self $ver[2] ".php_uname()." PHP ".phpversion()."/".php_sapi_name());	// Fake UserAgent
 define($ver[1],1);					// Feste Kennung für Plugins etc.
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
 $help = "\n\nWeitere Hilfe bekommen Sie mit der -h Option";
 $pmax = count($argv);	// Anzahl der Parameter
 $pset = 1;		// Optionszähler

# Drag'n'Drop Modus
 if(ifset($cfg['drag']) and $pset+1 == $pmax and file_exists($argv[$pset])) {
  $drag = explode(',',$cfg['drag']);
  array_splice($argv,$pmax,0,explode(' ',$drag[1]));
  array_splice($argv,$pset,0,explode(' ',$drag[0]));
  $pmax += count($drag[0])+count($drag[1]);
 }

# Fritz!Box Parameter ermitteln und auswerten
 if($pset+1 < $pmax and @preg_match('/^
	(?:([^:]+):)?	(?:([^@]+)@)?
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
   if($var == 'o' and $array[2][$key])
    $cfg['oput'] = $array[2][$key];
   if($var == 's' and preg_match('/^[\da-f]{16}$/i',$array[2][$key]))
    $cfg['bsid'] = $cfg['sid'] = $array[2][$key];
  }
 }

 # PHP-Fehler Protokollieren
 if($cfg['dbug']/(1<<8)%2) {
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
 }
 else
  error_reporting(0);					// Fehler-Meldungen deaktivieren

# Char ermitteln
 if(ifset($cfg['char'],'auto')) {
  if($var = ifset($_SERVER['LANG'],'/(UTF-?8)|((?:iso-)?8859-1)/i') and ($var[1] and !isset($cfg['utf8'])) or ifset($var[2]))
   $cfg['char'] = ($var[1]) ? 'utf8' : 'ansi';
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']))
   $cfg['char'] = 'oem';
  elseif(isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']) and !isset($cfg['utf8']))
   $cfg['char'] = 'utf8';
  else
   $cfg['char'] = '7bit';
 }

# Consolen Breite automatisch ermitteln
 if($cfg['wrap'] == 'auto') {
  if(isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']))
   $cfg['wrap'] = (int)exec('tput cols');
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']) and (exec('mode con',$var) or true)
	and is_array($var) and preg_match_all('/(?:(zeilen|lines)|(spalten|columns)|(code\s?page)):\s*(\S+)/',strtolower(implode('',$var)),$val))
   foreach($val[4] as $key => $var) {
    if(ifset($val[2][$key]))
     $cfg['wrap'] = $var;
    if(ifset($val[3][$key]))
     $cfg['char'] = "cp$var";
   }
  if($cfg['wrap'] == 'auto')
   $cfg['wrap'] = 0;
 }

# Auto-Update (Check)
 if($cfg['upda'] and $uplink and time()-filemtime($script) > $cfg['upda']) {
  if($fbnet = request('GET',"$uplink[1]md5",0,0,$uplink[0],80)
	and preg_match("/\((\w+)\s([\d.]+)\)/",$fbnet,$var) and floatval($var[2]) > $ver[2])
   out("Ein Update ist verfügbar ($ver[1] $ver[2]) - Bitte nutzen Sie die UpGrade Funktion");
  else
   @touch($script);
 }

# Parameter auswerten
 if($pset < $pmax and preg_match('/^
	((?<bi>BoxInfo|bi)	|(?<pi>PlugIn|pi)	|(?<lio>Log(in|out)|l[io])	|(?<d>Dial|d)	|(?<i>I(nfo)?)	|(?<gip>G(et)?IP)
	|(?<rc>ReConnect|rc)	|(?<sd>SupportDaten|sd)	|(?<ss>(System)?S(tatu)?s)	|(?<t>Test|t)	|(?<k>K(onfig)?)|(?<ug>UpGrade|ug)
	)$/ix',$argv[$pset++],$val)) {			## Modes mit und ohne Login ##
  if($cfg['dbug']/(1<<3)%2) {				// Debug Parameter
   dbug('$argv');
   dbug($val);
  }
  if(ifset($val['bi']) and $val['bi']) {		// Jason Boxinfo
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [BoxInfo|bi]\n\n"
	."Beispiele:\n"
	."$self boxinfo\n"
	."$self 169.254.1.1 bi");
   elseif($data = request('GET-array','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data[1],$array)) {
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
    $array[0][] = 'Aktuelle Uhrzeit ... '.date('d.m.Y H:i:s',strtotime($data['Date']));
    out("\nBoxinfo:\n".implode("\n",$array[0]));
   }
   else
    out("Keine Informationen erhalten");
  }
  elseif(ifset($val['gip'])) {				// Get Extern IP
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [GetIP|gip]\n\n"
	."Beispiele:\n"
	."$self getip\n"
	."$self 169.254.1.1 gip");
   elseif($var = getexternalip())
    out("IPv4: $var");
   elseif($var = errmsg(0,'getexternalip'))
    out($var);
   else
    out("Keine Externe IP-Adresse verfügbar");
  }
  elseif(ifset($val['i'])) {				// Info (Intern)
   if(ifset($cfg['help']))
    out("$self [Info|i] <php|Datei>\n\n"
	."Beispiel:\n"
	."$self info\n"
	."$self info php\n"
	."$self info <Datei>\n"
	."$self i -d");
   elseif($pset < $pmax and preg_match('/^(php)$/',$argv[$pset])) {	// PHPInfo() ausgeben
    ob_start();
    phpinfo();
    $data = ob_get_contents();
    ob_clean();
    out($data);
   }
   elseif($cfg['dbug']) {				// DEBUG: $argv & $cfg ausgeben mit Login-Test
    dbug('$argv');
    $sid = $cfg['sid'] = login();
    dbug('$cfg');
    if($sid)
     logout($sid);
   }
   else {						// FB_Tools-Version, PHP Kurzinfos und Hashes ausgeben
    $var = array("PHP ".phpversion()."/".php_sapi_name(),php_uname()."\n\n");
    out("$ver[0]\n".implode(($cfg['wrap'] and strlen($var[0].$var[1])+3 < $cfg['wrap']) ? " - " : "\n",$var));
    $file = ($pset < $pmax and file_exists($argv[$pset])) ? $argv[$pset++] : $script;
    $data = file_get_contents($file);
    $array = array('File' => $file,'Size' => number_format(filesize($file),0,0,'.')." Bytes",'CRC32' => crc_32($data),'MD5' => md5($data),'SHA1' => sha1($data));
    if(function_exists('hash') and $file != $script)
     $array = array_merge($array,array('SHA256' => hash('sha256',$data),'SHA512' => hash('sha512',$data)));
    $max = max(array_map('strlen',array_keys($array)));
    foreach($array as $key => $var)
     out(str_pad("$key:",$max+2,' ').(($cfg['wrap'] and $cfg['wrap'] < strlen($var)+$max+2) ? substr_replace($var,str_pad("\n",$max+3,' '),strlen($var)/2,0) : $var));
   }
  }
  elseif(ifset($val['rc'])) {				// ReConnect
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [ReConnect|rc]\n\n"
	."Beispiele:\n"
	."$self reconnect\n"
	."$self 169.254.1.1 rc");
   else
    out(($var = forcetermination()) ? "Ok" : errmsg(0,'getexternalip'),1);
  }
  elseif(ifset($val['ss'])) {				// SystemStatus
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [SystemStatus|Status|ss] <supportcode>\n\n"
	."Beispiele:\n"
	."$self systemstatus\n"
	."$self 169.254.1.1 status\n"
	."$self ss \"FRITZ!Box Fon WLAN 7390-B-010203-040506-000000-000000-147902-840522-22574-avm-de\"");
   else
    out(($var = supportcode(($pset < $pmax) ? $argv[$pset] : false)) ? $var : errmsg(0,'supportcode'));
  }
  elseif(ifset($val['k'])) {				// Konfig
   if(ifset($cfg['help']) or $pset == $pmax)
    out("$self <user:pass@fritz.box:port> [Konfig|k] [Funktion] <Datei|Ordner> <Kennwort>

Funktionen:
ExPort          <Datei>  <Kennwort> - Konfig exportieren(1)
ExPort-DeCrypt  <Datei>  <Kennwort> - Konfig entschlüsseln und exportieren(1,3)
ExTrakt         <Ordner> <Kennwort> - Konfig entpackt anzeigen/exportieren(1)
ExTrakt-DeCrypt <Ordner> <Kennwort> - Konfig entpackt entschl./anz./exp.(1,3)
File            [Datei]  <Ordner> - Konfig-Infos aus Datei ausgeben(2)
File            [Ordner] [File]   - Konfig-Ordner in Datei zusammenpacken(2)
File-CalcSum    [Ordner] [File]   - Veränderter Konfig-Ordner Zusammensetzen(2)
File-JSON       [Datei] [Datei]   - Konfig-Daten in JSON konvertieren(2)
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
   elseif(preg_match('/^(						# 1:Alle
	|i(p|mport)(cs|-calcsum)?					# 2:Import 3:CalcSum
	|e(p|xport)(?:(dc|-de(?:crypt|code))?)				# 4:Export 5:DeCrypt
	|(et|(?:extra[ck]t))?(?:(dc|-de(?:crypt|code))?)		# 6:Extrakt 7:DeCrypt
	|(f(?:ile)?)(?:(cs|-calcsum)?|(dc|-de(?:crypt|code))?|(-?json)?)# 8:File 9:CalcSum 10:DeCrypt 11:JSON
		)$/ix',$argv[$pset++],$mode)) {
    if($cfg['dbug']/(1<<3)%2)				// Debug Parameter
     dbug($mode);
    $mode = array_pad($mode,12,null);
    $file = ($pset < $pmax) ? $argv[$pset++] : false;
    $pass = ($pset < $pmax) ? $argv[$pset++] : false;
    if(($mode[2] or $mode[4] or $mode[6])) {		// Login Optionen
     if($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
      if($mode[5] or $mode[7]) {			// Kennwort-Entschlüsselung
       if($cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {
        if(!$pass)					// Im DeKode-Modus kein leeres Kennwort zulassen
         $pass = ($cfg['pass']) ? $cfg['pass'] : 'geheim';
       }
       else {
        out("Entschlüsselung wird nicht unterstützt");
        $mode[5] = $mode[7] = false;
       }
      }
      if($mode[4]) {					// Export
       if(is_dir($file)) {				// Im Ordner schreiben
        if($cfg['dbug']/(1<<0)%2)
         dbug("Wechsle zu Ordner $file");
        chdir($file);
        $file = false;
       }
       out(($var = cfgexport($file,$pass,'save',$mode[5])) ? $var : errmsg(0,'cfgexport'));	// File-Export
      }
      elseif($mode[6]) {				// Extrakt
       if($file and !file_exists($file)) {
        if($cfg['dbug']/(1<<0)%2)
         dbug("Erstelle Ordner $file");
        mkdir($file);					// Neues Verzeichniss erstellen
       }
       if(is_dir($file)) {				// Current-Dir setzen
        if($cfg['dbug']/(1<<0)%2)
         dbug("Wechsle zu Ordner $file");
        chdir($file);
       }
       out(($var = cfgexport($file,$pass,'info',$mode[7])) ? $var : errmsg(0,'cfgexport'));	// Split-Export
      }
      elseif($mode[2] and $file and file_exists($file))	// Import-Konfig
       out((cfgimport($file,$pass,$mode[3])) ? "Konfig wurde hochgeladen und wird nun bearbeitet" : errmsg(0,'cfgimport'));
      else
       out("$file kann nicht geöffnet werden!");
      if(!ifset($cfg['bsid']))
       logout($sid);
     }
     else
      out(errmsg(0,'login'));
    }
    elseif($mode[8] and !$mode[10] and !$mode[11] and is_file($file) and $data = file_get_contents($file)) {	// Converter-Modus File -> Dir
     if($pass) {		// Verzeichniss angegeben ?
      if(!file_exists($pass))
       mkdir($pass);		// Neues Verzeichniss erstellen
      if(is_dir($pass))
       chdir($pass);		// Verzeichniss benutzen
     }
     out(($data = cfginfo($data,$pass)) ? $data : "Keine Konfig Export-Datei angegeben");
    }
    elseif($mode[8] and !$mode[10] and !$mode[11] and is_dir($file) and $pass and (!file_exists($pass) or is_file($pass)))	// Converter-Modus Dir -> File
     out(($data = cfgmake($file,$mode[9],$pass)) ? $data : "Kein Konfig Export-Verzeichnis angegeben");
    elseif($mode[8] and $mode[10] and !$mode[11] and $pass and is_file($file) and $data = file_get_contents($file)) {		// Kennwörter Entschlüsseln
     if($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
      if($cfg['fiwa'] > 500 and ifset($cfg['boxinfo']['Name'],'/FRITZ!Box/i')) {// Entschlüsselung durchführen
       if($data = konfigdecrypt($data,$pass,$sid)) {
        out(showaccessdata($data));						// Daten als Text Präsentieren
        if($pset < $pmax)							// Optional entschlüsselte Daten speichern
         file_put_contents($argv[$pset++],$data);
       }
       else
        out("Entschlüsselung fehlgeschlagen, möglicherweise ist das Kennwort falsch");
      }
      else
       out("Entschlüsselung wird nicht unterstützt");
      if(!ifset($cfg['bsid']))
       logout($sid);
     }
     else
      out(errmsg(0,'login'));
    }
    elseif($mode[8] and $mode[11] and is_file($file) and $pass)
     if(function_exists('json_encode'))
      out(($data = file_get_contents($file) and $array = konfig2array($data))
	? ((file_put_contents($pass,json_encode($array))) ? "Konfig-Datei erflogreich in JSON konvertiert" : errmsg(0,'konfig2array'))
	: errmsg(0,'konfig2array'));
     else
      out('JSON wird von PHP '.phpversion()." nicht unterstützt");
    else
     out("Parameter-Ressourcen zu $mode[0] nicht gefunden oder nicht korrekt angegeben$help");
   }
   else
    out("Unbekannte Funktionsangabe für Konfig$help");
  }
  elseif(ifset($val['d'])) {				// Dial
   if(ifset($cfg['help']) or $pset == $pmax)
    out("$self <user:pass@fritz.box:port> [Dial|d] [Rufnummer] <Telefon>

Telefon:
1-4 -> FON 1-4 | 50 -> ISDN/DECT | 51-58 -> ISDN 1-8 | 60-65 -> DECT 1-6

Beispiele:
$self password@fritz.box dial 0123456789 50
$self username:password@fritz.box dial \"#96*7*\"
$self 169.254.1.1 d -");
   elseif($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
    out(dial($argv[$pset++],(($pset < $pmax) ? $argv[$pset++] : errmsg(0,'dial'))));
    if(!ifset($cfg['bsid']))
     logout($sid);
   }
   else
    out(errmsg(0,'login'));
  }
  elseif(ifset($val['sd'])) {				// Supportdaten
   if(ifset($cfg['help']))
    out("$self <user:pass@fritz.box:port> [SupportDaten|sd] <filename>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box supportdaten support.txt\n"
	."$self 169.254.1.1 sd");
   elseif($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
    if(!supportdaten(($pset < $pmax) ? $argv[$pset] : false))
     out(errmsg(0,'supportdaten'));
    if(!ifset($cfg['bsid']))
     logout($sid);
   }
   else
    out(errmsg(0,'login'));
  }
  elseif(ifset($val['lio'])) {				// Manuelles Login / Logout
   if(ifset($cfg['help']))
    out("$self <user:pass@fritz.box:port> [LogIn|LogOut|li|lo] <-s:sid>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box login > sid.txt\n"
	."$self fritz.box logout -s:0123456789abcdef");
   elseif(preg_match('/l(?:og)?(?:(in?)|(o(?:ut)))/',$val['lio'],$var)) {
    if(ifset($var[1]))
     out(login());
    elseif(ifset($var[2]))
     logout($cfg['sid']);
    else
     out('Mmmm...');
   }
   else
    out('Häää?');
  }
  elseif(ifset($val['pi'])) {				// Plugin
   if($pset == $pmax)
    out("$self <user:pass@fritz.box:port> [PlugIn|pi] [Script-Datei] <...>\n\n"
	."Beispiele:\n"
	."$self password@fritz.box plugin fbtp_led.php off\n"
	."$self fritz.box plugin fbtp_test.php\n\n"
	."WARNUNG: Es gibt keine Prüfung auf Malware!");
   elseif($pset < $pmax and file_exists($argv[$pset]))
    include $argv[$pset++];
   else
    out("Kein Plugin-Script angegeben");
  }
  elseif(ifset($val['ug'])) {				// UpGrade (Intern)
   if(ifset($cfg['help']))
    out("$self [UpGrade|ug] <Check>\n\n"
	."Beispiele:\n"
	."$self upgrade check\n"
	."$self ug\n"
	."$self ug c");
   elseif($uplink and $fbnet = request('GET-array',"$uplink[1]md5",0,0,$uplink[0],80)) {	// Update-Check
    $coo = (ifset($fbnet['X-Cookie'])) ? "\n".$fbnet['X-Cookie'] : "";
    if(preg_match("/((\d\d)\.(\d\d)\.(\d{4}))\s[\d:]+\s*\((\w+)\s([\d.]+)\)(?:.*?(\w+)\s\*\w+\.$ext(?=\s))?/s",$fbnet[1],$up)) {
     if(intval($up[4].$up[3].$up[2]) > $ver[8] or floatval($up[6]) > $ver[2]) {
      out("Ein Update ist verfügbar: $up[5] $up[6] vom $up[1]");
      if($pset == $pmax) {
       out("Installiere Update ... ",2);
       $manuell = "!\nBitte installieren Sie es von http://$uplink[0]/.dg manuell!";
       if(ifset($up[7]) and $fbnet = @request('GET',"$uplink[1]$ext.gz",0,0,$uplink[0],80)) {
        $rename = preg_replace('/(?=(\.\w+)?$)/','.bak',$script,1);
        if(function_exists('gzdecode') and $var = @gzdecode($fbnet) and md5($var) == $up[7] and @rename($script,$rename)) {// Update ab PHP5
         file_put_contents($script,$var);
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
         if(md5($var) == $up[7] and @rename($script,$rename) and $fp = fopen($script,'w')) {	// Script überschreiben
          fwrite($fp,$var);
          fclose($fp);
          $var = true;
         }
        }
        if($var === true) {
         chmod($script,intval(fileperms($script),8));
         out("abgeschlossen!");
        }
        else
         out("fehlgeschlagen$manuell");
       }
       else
        out("Automatisches Update ist nicht verfügbar$manuell");
      }
     }
     else {
      @touch($script);										// Aktuelles Datum setzen
      out("Kein neues Update verfügbar!");
     }
    }
    else
     out("Update-Server sagt NEIN!");
    out($coo);
   }
    else
     out("Computer sagt NEIN!");
  }
  elseif(ifset($val['t'])) {				// Test
   if(ifset($cfg['help']))
    out("$self [Test|t]\n\n"
	."Beispiele:\n"
	."$self Test\n"
	."$self t\n");
   else
    out('Test');
  }
  else
   out("Möglichweise ist ein unerwarterer und unbekannter, sowie mysteriöser Fehler aufgetreten :-)");
 }
 elseif(ifset($cfg['dbug'])) {				// DEBUG: $argv & $cfg ausgeben
  dbug('$argv');
  dbug('$cfg');
 }
 else {
  out("$self user:pass@fritz.box:port [mode] <mode-parameter> ... <option>".((ifset($cfg['help'])) ? "

Modes:
BoxInfo      - Modell, Firmware-Version und MAC-Adresse ausgeben
Dial         - Rufnummer wählen(2)
GetIP        - Aktuelle externe IPv4-Adresse ausgeben(1)
Info         - FB-Tools Version, PHP Version, MD5/SHA1 Checksum
Konfig       - Einstellungen Ex/Importieren(2,3)
Login/Logout - Manuelles Einloggen für Scriptdateien(2)
PlugIn       - Weitere Funktion per Plugin-Script einbinden
ReConnect    - Neueinwahl ins Internet(1)
SupportDaten - AVM-Supportdaten Speichern(2)
SystemStatus - Modell, Version, Laufzeiten, Neustarts und Status ausgeben(3)
UpGrade      - FB-Tools Updaten oder auf aktuelle Version prüfen(3)

(1) Aktiviertes UPnP erforderlich / (2) Anmeldung mit Logindaten erforderlich
(3) Teilweise ohne Fritz!Box nutzbar / [ ] Pflicht / < > Optional

Optionen:
-b:[Bytes]    - Buffergröße ($cfg[sbuf])
-c:[CodePage] - Kodierung der Umlaute ($cfg[char])
-d            - Debuginfos
-h            - Hilfe
-o:[Datei]    - Ansi-Ausgabe in Datei
-s:[SID]      - Manuelle SID Angabe (Für Scriptdateien)
-t:[Sekunden] - TCP/IP Timeout ($cfg[tout])
-w:[Breite]   - Wortumbruch ($cfg[wrap])

Beispiele:
$self secret@fritz.box supportdaten
$self hans:geheim@fritz.box konfig export
$self 192.168.178.1 systemstatus

Eine Anleitung finden Sie auf $ver[7]/.dg" : $help));
 }
 if($cfg['dbug'] and $cfg['error'])			// Fehler bei -d ausgeben
  dbug("Fehler:\n".print_r($cfg['error'],true));
}

?>
