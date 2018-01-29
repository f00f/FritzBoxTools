#!/usr/bin/php
<?php $ver = "fb_tools 0.20 (c) 20.03.2017 by Michael Engelke <http://www.mengelke.de>"; #(charset=iso-8859-1 / tabs=8 / lines=lf)

if(!isset($cfg)) {					// $cfg schon gesetzt?
 $cfg = array(
	'host'	=> 'fritz.box',		// Fritz!Box-Addresse
	'pass'	=> 'password',		// Fritz!Box Kennwort
	'user'	=> false,		// Fritz!Box Username (Optional)
	'port'	=> 80,			// Fritz!Box HTTP-Port (Normalerweise immer 80)
	'fiwa'	=> 100,			// Fritz!Box Firmware (Nur Intern)
	'upnp'	=> 49000,		// Fritz!Box UPnP-Port (Normalerweise immer 49000)
	'pcre'	=> 64*1024*1024,	// pcre.backtrack_limit
	'sbuf'	=> 4096,		// TCP/IP Socket-Buffergröße
	'tout'	=> 30,			// TCP/IP Socket-Timeout
	'upda'	=> 60*60*24*100,	// Auto-Update-Check Periode (Kein Update-Check: 0)
	'wrap'	=> 'auto',		// Manueller Wortumbruch (Kein Umbruch: 0)
	'char'	=> 'auto',		// Zeichenkodierung der Console (auto/ansi/oem/utf8)
	'dbfn'	=> 'debug#.txt',	// Template für Debug-Dateien
	'time'	=> 'Europe/Berlin',	// Zeitzone festlegen
	'drag'	=> 'konfig fcs,-d',	// Drag'n'Drop-Modus
	'help'	=> false,		// Hilfe ausgeben
	'dbug'	=> false,		// Debuginfos ausgeben
	'oput'	=> false,		// Ausgaben speichern
	'zlib'	=> array('mode' => -1),	// ZLib-Funktionen (mode: packlevel)
#	'error' => array(),		// Fehlerlogs
#	'preset'=> array(),		// Leere Benutzerkonfiguration
#	'boxinfo'=>array(),		// Leere Boxinfo Daten
	'usrcfg'=> 'fb_config.php',	// Filename der Benutzerkonfiguration
 );
}
if(!function_exists('array_combine')) {			// http://php.net/array_combine
 function array_combine($key,$value) {
  if(is_array($key) and is_array($value) and count($key) == count($value))
   while(list($kk,$kv) = each($key) and list($vk,$vv) = each($value))
    $array[$kv] = $vv;
  return ($array) ? $array : false;
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
     return null;
    }
   }
   else
    fputs($fp,$data);
   fclose($fp);
   $fp = strlen($data);
  }
  return $fp;
 }
}
if(!function_exists('gzdecode')) {			// http://php.net/gzdecode (Workaround)
 function gzdecode($data) {
  global $cfg;
  if($tmp = tempnam(null,'gz') and $fp = fopen($tmp,'w')) {	// Gepackte Daten speichern
   fwrite($fp,$data);
   fclose($fp);
   $data = null;
   if($fp = $cfg['zlib']['open']($tmp,'rb')) {			// Daten entpackt lesen
    while(!$cfg['zlib']['eof']($fp))
     $data .= $cfg['zlib']['read']($fp,$GLOBALS['cfg']['sbuf']);
    $cfg['zlib']['close']($fp);
   }
   @unlink($tmp);						// Überreste löschen
  }
  return $data;
 }
}
function file_read($file) {				// (Gepackte) Datei lesen
 global $cfg;
 dbug("Lese File: $file",9);
 if($file and $fp = $cfg['zlib']['open']($file,'r')) {
  $data = "";
  while(!$cfg['zlib']['eof']($fp))
   $data .= $cfg['zlib']['read']($fp,$cfg['sbuf']);
  $cfg['zlib']['close']($fp);
  return $data;
 }
 return false;
}
function file_write($file,$data) {			// (Gepackte) Datei schreiben
 global $cfg;
 if($file and ifset($file,'/\.gz$/i') and $fp = gzopen($file,'w'.$cfg['zlib']['mode'])) {
  $data = $cfg['zlib']['write']($fp,$data);
  $cfg['zlib']['close']($fp);
  return $data;
 }
 else
  return file_put_contents($file,$data);
}
function crc_32($str,$file=false) {			// Berechnet die CRC32 Checksume von einen String (Optional von einer Datei)
 return str_pad(sprintf('%X',crc32(($file) ? file_get_contents($str) : $str)),8,0,STR_PAD_LEFT);
}
function ifset(&$x,$y=false) {				// Variabeln prüfen und Optional vergleichen
 return (isset($x) and ($x or $x != '')) ? (($y and is_string($x) and $y{0} == '/' and preg_match($y,$x,$z)) ? $z : (($y) ? ((is_array($x)) ? (($y{0} == '/') ? preg_grep($y,$x) : array_search($y,$x)) : $x == $y) : ((is_array($x)) ? count($x) : !$y))) : false;
}
function preg_array($x,$y,$z=0,$w=array()) {		// Durchsucht einen Array mit Regulären Ausdruck
 foreach($y as $k => $v)				// $z: 0 -> value / 1 -> key / 2 -> values / 3 -> keys
  if(preg_match($x,($z%2) ? $k : $v))
   if($z/2%2)
    $w[$k] = $v;
   else
    return $k;
 return $w;
}
function out($str,$mode=0) {				// Textconvertierung vor der ausgabe (mode: 0 -> echo / 1 -> noautolf / 2 -> debug)
 global $cfg;
 if($str) {
  if(is_array($str))
   $str = print_r($str,true);
  if(!($mode/(1<<1)%2) and preg_match('/\S$/D',$str))		// AutoLF
   $str .= "\n";
  if($mode/(1<<2)%2)						// Unnötige Whitespaces im Debug-Modus löschen
   $str = preg_replace('/(?<=\n\n|\r\n\r\n)\s+$/','',$str);
  if($cfg['oput'] and !($mode/(1<<2)%2))			// Ausgabe speichern
   file_put_contents($cfg['oput'],$str,8);
  if((int)$cfg['wrap'] and !ifset($cfg['char'],'/7bit|(13{2}|utf)7/'))	// Wortumbruch
   $str = wordwrap($str,$cfg['wrap']-1,"\n",true);
  if(ifset($cfg['char'],'/^(dos|oem|c(odepage|p)?(437|850))$/') and preg_match_all('/(\s|.)(\s+|.)/',base64_decode("5IT2lPyB3+HEjtaZ3JqnFQoNCg"),$m))
   $str = str_replace($m[1],$m[2],$str);
  elseif(ifset($cfg['char'],'/utf8|c(odepage|p)65001/'))
   $str = utf8_encode($str);
  elseif($cfg['char'] == 'html' and preg_match_all('/(.)([\da-z#]+)/','&amp<lt>gt"quot\'#39äaumlöoumlüuumlßszligÄAumlÖOumlÜUuml',$m))
   $str = str_replace($m[1],preg_replace('/.+/','&$0;',$m[2]),$str);
  elseif(ifset($cfg['char'],'/^\d+$/') and dechex($cfg['char']) == 539
   and preg_match_all('/([a-z\d])(.)|(.)(..)/i','1I2Z3E4A5S6G7T8B9g0Oa4A4b8B8c<C(e3E3g6G6h#H#i!I!l1L1o0O0q9Q9s5S5t7T7x+X+z2Z2ä4:Ä4:ö0:Ö0:üu:ÜU:ß55§55',$m)
   and preg_match_all('/(.)(.+)/',implode("\n",$m[0]),$m))
    $str = strtr($str,array_combine($m[1],$m[2]));
  elseif(!ifset($cfg['char'],'/^(ansi|(codepage|cp)1252|iso.?8859.?1|ascii)$/i') and preg_match_all('/(.)(..)/','äaeöoeüueßssÄAeÖOeÜUe§SS',$m))
   $str = str_replace($m[1],$m[2],$str);
  if((int)$cfg['wrap'] and ifset($cfg['char'],'/7bit|(13{2}|utf)7/'))	// Wortumbruch
   $str = wordwrap($str,$cfg['wrap']-1,"\n",true);
 }
 return ($mode/(1<<0)%2) ? $str : print $str;
}
function dbug($str,$level=0,$mode=4) {			// Debug-Daten ausgeben/speichern (mode: 3 -> NoTime)
 global $cfg;
 if($cfg['dbug']/(1<<$level)%2) {			// Nur Entsprechenden Debug-Level ausgaben
  if(is_string($mode))					// Entweder Mode-Angabe oder Dateiname
   if(preg_match('/^(\d+),(.+)$/',$mode,$var)) {
    $mode = $var[1];
    $file = $var[2];
   }
   else {
    $file = $mode;
    $mode = 4;
   }
  else
   $file = false;
  $time = ($cfg['dbug']/(1<<2)%2 and !($mode/(1<<3)%2)) ? number_format(array_sum(explode(' ',microtime()))-$cfg['time'],3,',','.').' ' : '';
  if($cfg['dbug']/(1<<1)%2 and $cfg['dbfn'] and $file)	// Debug: Array in separate Datei sichern
   if(strpos($file,'#') and is_array($str))
    foreach($str as $key => $var)			// Debug: Array in mehrere separaten Dateien sichern
     file_put_contents($cfg['dbcd'].str_replace('#',"-".str_replace('#',$key,$file),$cfg['dbfn']),$time.((is_array($var)) ? print_r($var,true) : $var),8);
   else
    file_put_contents($cfg['dbcd'].str_replace('#',"-$file",$cfg['dbfn']),$time.((is_array($str)) ? print_r($str,true) : $str),8);	// Alles in EINE Datei Sichern
  else {
   if(is_string($str)) {
    if(preg_match('/^\$(\w+)$/',$str,$var) and isset($GLOBALS[$var[1]]))	// GLOBALS Variable ausgeben
     $str = "$str => ".(is_array($GLOBALS[$var[1]]) ? print_r($GLOBALS[$var[1]],true) : $GLOBALS[$var[1]]);
    elseif(!($mode/(1<<1)%2) and preg_match('/\S$/D',$str))// AutoLF
     $str .= "\n";
   }
   elseif(is_array($str))
    $str = print_r($str,true);
   if($cfg['dbug']/(1<<1)%2 and $cfg['dbfn']) {		// Debug: Ausgabe/Speichern
    file_put_contents($cfg['dbcd'].str_replace('#','',$cfg['dbfn']),$time.$str,8);
    if(!$level)						// Nur Level 0 Ausgeben
     out($time.$str,$mode | 4);
   }
   else
    out($time.$str,$mode | 4);
  }
 }
}
function errmsg($msg,$name='main') {			// Fehlermeldung(en) Sichern
 global $cfg;
 if($msg) {			// Fehlermeldung speichern
  dbug("Fehler: $msg notieren",9);
  $cfg['error'][$name][] = trim($msg);
 }
 else {				// Fehlermeldung abrufen
  dbug("Fehler: $name suchen",9);
  while(isset($cfg['error'][$name]) and is_array($cfg['error'][$name]))	// Fehlermeldung vorhanden?
   if($val = end($cfg['error'][$name]) and preg_match('/^\w+$/',$val))	// Möglicher Rekusive Fehlermeldung?
    $name = $val;		// Nächste Fehlermeldung suchen
   else
    return $val;		// Fehlermeldung ausgeben
 }
 return false;			// Funktion fehlgeschlagen
}
function makedir($dir,$mode=1) {			// Erstellt ein Verzeichnis und wechselt dorthin
 if(!$dir or $dir == '.')				// Self-Dir nicht bearbeiten
  return true;
 $dir = preg_replace('/[\\\\\/]$/','',$dir);		// Abschlussshlash entfernen
 if(strpos($dir,'%') !== false)				// strftime auflösen (Problematische Zeichen werden umgewandelt)
  $dir = strftime($dir);
 $dir = preg_replace('/[<:*|?">]+/','-',$dir);
 if(!file_exists($dir)) {				// Neues Verzeichniss erstellen
  if($mode)						// Debug-Meldung unterdrücken
   dbug("Erstelle Ordner $dir");
  $dirs = preg_split('/[\\\\\/]/',$dir);		// Verzeichniskette erstellen
  $val = '';
  foreach($dirs as $var) {
   $val .= $var;
   if($val and !file_exists($val))
    mkdir($val);
   $val .= '/';
  }
 }
 if($mode and is_dir($dir)) {				// Aktuelles-Dir setzen
  dbug("Wechsle zu Ordner $dir");
  chdir($dir);
  $mode = 0;
 }
 return ($mode) ? false : $dir;
}
function tar2array($file) {				// Liest ein Tar-Archiv als Array ein
 global $cfg;
 if(file_exists($file) and preg_match($cfg['ptar'],$file) and $fp = $cfg['zlib']['open']($file,'r')) {
  dbug("Entpacke Tar-Archiv",9);
  $tar = array();
  while($meta = $cfg['zlib']['read']($fp,512) and preg_match('/^[^\0]+/',substr($meta,0,100),$name)) {
   $data = substr_replace($meta,"        ",148,8);
   for($crc=$a=0; $a < 512; $crc += ord($data{$a++}));
   if($crc != octdec(substr($meta,148,6)))
    return errmsg("Defektes Tar-Archiv",__FUNCTION__);
   if($size = octdec(substr($meta,124,11)))
    $data = $cfg['zlib']['read']($fp,$size + 512 - $size % 512);
   if(!$meta{156})
    $tar[$name[0]] = substr($data,0,$size);
  }
  $cfg['zlib']['close']($fp);
  dbug($tar,9,'8,tar2array-#');
  return $tar;
 }
 return errmsg("TAR-Archiv nicht gefunden oder läßt sich nicht öffnen",__FUNCTION__);
}
function data2tar($name,$data='',$time=0) {		// Erstellt ein Tar-Header
 $data = str_pad($name,100,chr(0))."0100777".chr(0).str_repeat(str_repeat("0",7).chr(0),2).str_pad(decoct(strlen($data)),11,"0",STR_PAD_LEFT).chr(0)
	.str_pad(decoct($time),11,"0",STR_PAD_LEFT).chr(0)."        0".str_repeat(chr(0),100)."ustar".chr(0)."00".str_repeat(chr(0),247)
	.$data.str_repeat(chr(0),(512 - strlen($data) % 512) % 512);
 for($a=$b=0; $a<512; $a++)
  $b += ord($data[$a]);
 return substr_replace($data,str_pad(decoct($b),6,"0",STR_PAD_LEFT).chr(0)." ",148,8);
}
function request($method,$page='/',$body=false,$head=false,$host=false,$port=false) {	// HTTP-Request durchführen
 global $cfg;
 if(is_array($method))					// Restliche Parameter aus Array holen
  extract($method);
 foreach(array('host','port') as $var)			// Host & Port setzen
  if(!$$var)
   $$var = $cfg[$var];
 dbug("Request: $host:$port$page",9);
 if(!$head)						// Head Initialisieren
  $head = $cfg['head'];
 if($mode = preg_match('/^(\w+)(?:-(.+))?/',$method,$var)) {
  $method = strtoupper($var[1]);
  $mode = (isset($var[2])) ? $var[2] : (($var[1] == strtolower($var[1])) ? 'array' : false);	// Result-Modus festlegen
 }
 dbug("$host:$port ",5);
 if($fp = @fsockopen($host,$port,$errnr,$errstr,$cfg['tout'])) {	// Verbindung aufbauen
  if($cfg['tout'])
   stream_set_timeout($fp,$cfg['tout']);		// Timeout setzen
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
   $head['connection'] = "close";
  foreach($head as $key => $var)			// Header vorbereiten
   $head[$key] = ucwords($key).": $var";
  $head = "HTTP/1.1\r\n".implode("\r\n",$head)."\r\n";
  dbug("$method $page".(($cfg['dbug']/(1<<7)%2) ? " $head$body\n\n" : ''),5,'Request');	// Debug Request
  fputs($fp,"$method $page $head$body");		// Request Absenden
  if($mode == 'putonly')				// Nur Upload durchführen
   return fclose($fp);
  $rp = "";						// Antwort vorbereiten
  if(preg_match('/(?:save|down(?:load)?):(.*)/',$mode,$file)) {	// Download -> Datei
   while(!feof($fp) and $var = fread($fp,$cfg['sbuf'])) {
    $rp .= $var;
    if($pos = strpos($rp,"\r\n\r\n")) {			// Header/Content trennen
     $header = substr($rp,0,$pos);
     if(preg_match('!^HTTP/[\d.]+\s*(\d+)\s*(.+)!i',$header,$var) and $var[1] >= 400) {	// Blacklist erkennen
      fclose($fp);
      return errmsg("HTTP-Fehler: $var[1] $var[2]",__FUNCTION__);
     }
     if($rp = substr($rp,$pos+4) or !feof($fp)) {	// Nur weitermachen wenn noch Daten kommen
      $file[1] = preg_replace('/(?<=[\\\\\/])$|^\.?$/',(ifset($file[1],'/^[.\\\\\/]*$/')
	and preg_match('/^Content-Disposition:\s*(?:attachment;\s*)?filename=(["\']?)(.*?)\1\s*$/mi',$header,$var))
	? preg_replace('/[?\\\\\/<*>:"]+/','_',$var[2]) : basename(urldecode($page)),$file[1]);
      dbug("Downloade '$file[1]'".((preg_match('/Content-Length:\s*(\d+)/',$header,$var)) ? " ".number_format($var[1],0,'.',',')." Bytes" : ""));
      $a = strlen($rp);
      if($sp = fopen($file[1],'w')) {
       fputs($sp,$rp);
       while(!feof($fp) and $var = fread($fp,$cfg['sbuf'])) {
        fputs($sp,$var);
        $a += strlen($var);
        dbug(".",0,10);	// Download-Anzeige
       }
       fclose($sp);
       dbug("\n",0,8);	// Download-Anzeige abschließen
       $rp = $header;
       if(!$a) {	// Wurde wirklich was heruntergeladen?
        unlink($file[1]);
        return errmsg("Download Fehlgeschlagen: Keine Daten erhalten",__FUNCTION__);
       }
      }
      else
       return errmsg("$file[1] kann nicht zum Schreiben geöffnet werden",__FUNCTION__);
     }
     else
      return errmsg("Download abgebrochen: Keine Daten erhalten",__FUNCTION__);
    }
   }
  }
  else							// Daten nur lesen
   while(!feof($fp) and $var = fread($fp,$cfg['sbuf'])) {
    $rp .= $var;
    dbug(".",6,10);	// Download-Anzeige
   }
  $meta = stream_get_meta_data($fp);
  if($meta['timed_out'] )
   $err = "Timeout: Keine Reaktion nach $cfg[tout] Sekunden";
  elseif(!$meta['eof'])					// Sollte nie auftreten
   $err = "Es wurden nicht alle Daten gelesen";
  if(ifset($err))
   dbug($meta,7,'Stream-Metadata');
  fclose($fp);
  dbug("\n",6,8);	// Download-Anzeige abschließen
  $fp = $rp;
  dbug((($cfg['dbug']/(1<<7)%2) ? $rp : preg_replace('/\n.*$/s','',$rp))."\n\n",6,'Request');	// Debug Response
  if(preg_match('!^HTTP/[\d.]+ (\d+) (.+)!i',$rp,$var) and $var[1] >= 400)	// Blacklist erkennen
   return errmsg("HTTP-Fehler: $var[1] $var[2]",__FUNCTION__);
  elseif($mode != 'raw' and preg_match('/^(http[^\r\n]+)(.*?)\r\n\r\n(.*)$/is',$rp,$array)) {	// Header vom Body trennen
   if($mode == 'array') {
    $fp = array($array[1]);
    if(count($array) > 0 and preg_match_all('/^([^\s:]+):\s*(.*?)\s*$/m',$array[2],$array[0]))
     foreach($array[0][2] as $key => $var)
      $fp[ucwords($array[0][1][$key])] = $var;
    $fp[1] = $array[3];
   }
   else
    $fp = $array[3];
  }
 }
 else
  $err = "$host:$port - Fehler $errnr: $errstr";
 if(ifset($err))
  errmsg($err,__FUNCTION__);
 return $fp;
}
function response($xml,$pass,$page=false) {		// Login-Response berechnen
 if(preg_match('!<Challenge>(\w+)</Challenge>!',$xml,$var)) {
  dbug("Kodiere Kennwort aus Challenge: $var[1]",9);
  $hash = "response=$var[1]-".md5(preg_replace('!.!',"\$0\x00","$var[1]-$pass"));
  if($page and $GLOBALS['cfg']['fiwa'] == 100)
   $GLOBALS['cfg']['fiwa'] = (substr($page,-4) == '.lua') ? '530' : '474';
  return $hash;
 }
 else
  return errmsg("Keine Challenge erhalten (".((substr($page,-4) == '.lua') ? 'lua' : 'xml').")",__FUNCTION__);
}
function login($pass=false,$user=false) {		// In der Fritz!Box einloggen
 global $cfg;
 foreach(array('user','pass') as $var)			// User & Pass setzen
  if(!$$var)
   $$var = $GLOBALS['cfg'][$var];
 $bug = (($user) ? " $user@" : " ")."$cfg[host] - Methode";
 $sid = $rp = $err = false;
 if($cfg['fiwa'] == 100) {	//  or $cfg['fiwa'] > 479
  dbug("Ermittle Boxinfos");
  if($data = request('GET-array','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data[1],$array)) {	// BoxInfos holen
   dbug($array,4);
   $cfg['boxinfo'] = array_combine($array[1],$array[2]);
   $cfg['boxinfo']['Time'] = strtotime($data['Date']);
   if(preg_match('/^\d+\.0*(\d+?)\.(\d+)(-\d+)?$/',$cfg['boxinfo']['Version'],$var))	// Firmware-Version sichern
    $cfg['fiwa'] = $var[1].$var[2];
  }
  elseif(!$data)
   $err = ($var = errmsg(0,'request')) ? ", $var" : ", keine Antwort";
 }
 if(!$err and $cfg['fiwa'] == 100 or $cfg['fiwa'] > 529) {	// Login lua ab 05.29
  dbug("Login$bug SID.lua (5.30)");
  $page = "/login_sid.lua";
  if($rp = request('GET',$page) and ($auth = response($rp,$pass,$page)) and $rp = request('POST',$page,(($user) ? "$auth&username=$user" : $auth)) and preg_match('/<SID>(\w+)<\/SID>/',$rp,$var) ) {
   if($cfg['fiwa'] == 100)
    $cfg['fiwa'] = 530;
   if(hexdec($var[1]) != 0)
    $sid = $var[1];
   else
    $err = ", SID.lua ist ungültig";
  }
  elseif(!$rp)
   $err = ", keine Antwort";
 }
 if(!$sid and !$err and ($cfg['fiwa'] == 100 or $cfg['fiwa'] > 473)) {	// Login cgi ab 04.74 (Zwischen 4.74 bis 5.29)
  dbug("Login$bug SID.xml (4.74)");
  $page = "/cgi-bin/webcm";
  $data = "getpage=../html/login_sid.xml";
  if($rp = request('GET',"$page?$data") and $auth = response($rp,$pass,$page) and preg_match('/<SID>(\w+)<\/SID>/',request('POST',$page,"$data&login:command/$auth"),$var)) {
   if($cfg['fiwa'] == 100)
    $cfg['fiwa'] = 474;
   if(hexdec($var[1]) != 0)
    $sid = $var[1];
   else
    $err = ", SID.xml ist ungültig";
  }
 }
 if(!$sid and !$err and ($cfg['fiwa'] == 100 or $cfg['fiwa'] < 490)) {	// Login classic bis 4.89 (z.B. FRITZ!Repeater N/G)
  dbug("Login$bug PlainText");
  if($var = request('POST',$page,"login:command/password=$pass") and !preg_match('/Anmeldung/',$var))
   $sid = true;
  elseif(!$var)
   $err = ", keine Antwort";
 }
 return ($cfg['sid'] = $sid) ? $sid : errmsg("Anmeldung fehlgeschlagen$err".((!ifset($pass,'/^[ -~]+$/')) ?
	"\nHinweis: Das Login-Kennwort enthält Sonderzeichen, die bei unterschiedlicher Zeichenkodierung Probleme bereiten können" : ""),__FUNCTION__);
}
function logout($sid=0) {				// Aus der Fritz!Box ausloggen
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 dbug("Logout ".$GLOBALS['cfg']['host']);
 if(is_string($sid) and $sid)				// Ausloggen
  request('GET',(($GLOBALS['cfg']['fiwa'] < 529) ? "/cgi-bin/webcm" : "/login_sid.lua"),"security:command/logout=1&logout=1&sid=$sid");
}
function supportcode($str = false) {			// Supportcode aufschlüsseln
 dbug("Enschlüssle Supportcode");
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
  :	errmsg(($var = ifset($str,'/<title>(.*?)<\/title>/i')) ? "Fehler: $var[1]" : "Unbekannt: $str",__FUNCTION__)) : errmsg('request',__FUNCTION__);
}
function upnprequest($page,$ns,$rq,$exp=false) {	// UPnP Request durchführen
 global $cfg;
 dbug("Setzte UPnP request ab: $page - $rq",9);
 if($rp = request(array(
	'method' => 'POST-array',
	'page' => $page,
	'body' => utf8_encode("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
	."<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\" s:encodingStyle=\"http://schemas.xmlsoap.org/soap/encoding/\">\n"
	."<s:Body><u:$rq xmlns:u=$ns /></s:Body>\n</s:Envelope>"),
	'head' => array_merge($GLOBALS['cfg']['head'],array('content-type' => 'text/xml; charset="utf-8"', 'soapaction' =>  "\"$ns#$rq\"")),
	'port' => $GLOBALS['cfg']['upnp']))) {
  if($cfg['fiwa'] != 100 and $var = ifset($rp['SERVER'],'/0*([1-9])\.(\d{2})\s*$/'))	// Firmware-Version auslesen
   $cfg['fiwa'] = intval($var[1].$var[2]);
  if($exp and is_string($exp))				// Nur einen Wert extrahieren
   return (preg_match("!<$exp>(.*?)</$exp>!",$rp[1],$var)) ? $var[1] : errmsg('Kein Erwartetes Ergebnis erhalten',__FUNCTION__);
  elseif(is_array($exp) and preg_match_all('!<(\w+)>(.+?)</\1>!',$rp[1],$array)) {	// Alle Werte in ein Array packen
   dbug($array,4,'upnprequest');
   foreach($array[1] as $key => $var)			// Ergebnisse zusammenstellen
    $exp[$var] = $array[2][$key];
   return $exp;
  }
  else							// Response RAW zurückgeben
   return $rp;
 }
 else							// Fehler aufgetreten
  return errmsg('request',__FUNCTION__);
}
function getupnppath($urn) {				// Helper für UPnP-Requests
 dbug("Ermittle UPnP Pfad: $urn",9);
 return ($rp = request(array('method' => 'GET', 'page' => '/igddesc.xml', 'port' => $GLOBALS['cfg']['upnp']))
	and preg_match("!<(service)>.*?<(serviceType)>(urn:[^<>]*".$urn."[^<>]*)</\\2>.*?<(controlURL)>(/[^<>]+)</\\4>.*?</\\1>!s",$rp,$var))
	? array($var[3],$var[5]) : errmsg('request',__FUNCTION__);
}
function getexternalip() {				// Externe IPv4-Adresse über UPnP ermitteln
 global $cfg;
 dbug("Ermittle IP-Adresse über UPnP");
 $ip = array();
 if($val = getupnppath('WANIPConnection')) {
  if($var = upnprequest($val[1],$val[0],'GetExternalIPAddress','NewExternalIPAddress'))
    $ip['IPv4'] = $var;
  if($cfg['fiwa'] <= 550) {				// IPv6 und DNS bei neueren Boxen
   $k = "X_AVM_DE_Get";
   if($var = upnprequest($val[1],$val[0],$k.'ExternalIPv6Address','NewExternalIPv6Address'))
    $ip['IPv6'] = $var;
   if($var = upnprequest($val[1],$val[0],$k.'IPv6Prefix',array()) and ifset($var['NewIPv6Prefix']))
    $ip['IPv6-Prefix'] = "$var[NewIPv6Prefix]/$var[NewPrefixLength]";
   if($var = upnprequest($val[1],$val[0],$k.'DNSServer',array()) and $var = preg_array('/New(IPv4)?DNSServer/',$var,3))
    $ip['DNSv4'] = implode(", ",$var);
   if($var = upnprequest($val[1],$val[0],$k.'IPv6DNSServer',array()) and $var = preg_array('/NewIPv6DNSServer/',$var,3))
    $ip['DNSv6'] = implode(", ",$var);
  }
  if($ip)
   return $ip;
 }
 return errmsg('request',__FUNCTION__);
}
function forcetermination() {				// Internetverbindungen über UPnP neu aufbauen
 dbug("WAN Neueinwahl über UPnP");
 return ($val = getupnppath('WANIPConnection') and $var = upnprequest($val[1],$val[0],'ForceTermination'))
  ? $var : errmsg('request',__FUNCTION__);
}
function saverpdata($file,$data,$name) {		// HTTP-Downloads in Datei speichern
 $file = preg_replace('/[<>\[\]:\/\\\\"*?|]/','_',($file) ? $file : ((@preg_match('/filename="(.*)"/',$data['Content-Disposition'],$var)) ? $var[1] : $name));
 $cfg['file'] = $file;
 dbug("Speichere Daten in $file",9);
 return file_put_contents($file,$data[1]);
}
function supportdata($file=false,$tm=false,$sid=0) {	// Supportdaten anfordern
 dbug("Hole Supportdaten");
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $array = array();
 if(!is_bool($sid))
  $array['sid'] = $sid;
 if($tm !== false and $GLOBALS['cfg']['fiwa'] >= 650) {	// Telemetrie ab OS6.5 aktivieren
  dbug("Telemetrie wird ".(($tm) ? '' : 'de') ."aktiviert");
  request('POST','/data.lua',"xhr=1&sid=$sid&lang=de&no_sidrenew=".(($tm) ? '&supportdata_enhanced=on' : '')."&support_plus=&oldpage=/support.lua");
 }
 $array['SupportData'] = '';
 $data = ($file) ? (bool)request("POST-save:$file",'/cgi-bin/firmwarecfg',$array) : request("POST-array",'/cgi-bin/firmwarecfg',$array);
 return ($data) ? $data : errmsg('request',__FUNCTION__);
}
function supportdataextrakt($data,$mode=0,$file='') {	// Supportdaten extrahieren
 global $cfg;
 $info = array();
 if(is_array($mode) and $mode[0] == 'sec') {
  $preg = '/^#{5} +BEGIN +SECTION +(\S+) *([^\r\n]+\s*)?(.*?)^#{5} +END +SECTION +\1\s+/sim';
  if(preg_match_all($preg,$data,$array)) {
   dbug($array,4,'SupportDataExtrakt-#');
   foreach($array[1] as $key => $var)
    if(trim($array[3][$key]))
     $info = array_merge($info,(($val = trim(preg_replace($preg,'',$array[3][$key]))) ? array($var => $array[2][$key].$val) : array()),supportdataextrakt($array[3][$key],array('sec')));
  }
 }
 elseif(substr($data,0,5) == '#####' and $array = supportdataextrakt($data,array('sec'))) {
  dbug("Zerlege Supportdaten");
  $mstr = $mlen = array(0,0);
  $val = $list = array();
  if($mode and $file) {					// Tar-Archiv Initialisieren
   $fp = ($mode == 1) ? fopen($file,'w') : $cfg['zlib']['open']($file,'w'.$cfg['zlib']['mode']);
   $date = (preg_match('/^#{5} +TITLE +Datum +(.+)/mi',$data,$var)) ? strtotime($var[1]) : 0;
  }
  foreach($array as $key => $var) {			// Maximale Längen ermitteln
   if($mode == 2 and $fp)
    $cfg['zlib']['write']($fp,data2tar("$key.txt",$var,$date));
   elseif($mode == 1 and $fp)
    fwrite($fp,data2tar("$key.txt",$var,$date));
   else
    file_put_contents("$key.txt",$var);
   $len = number_format(strlen($var),0,",",".");
   $list[] = array($key,$len);
   $c = (count($list)-1 < count($array)/2) ? 0 : 1;
   $mstr[$c] = max($mstr[$c],strlen($key));
   $mlen[$c] = max($mlen[$c],strlen($len));
  }
  if($mode and $fp) {					// Tar-Archiv abschließen
   $data = str_repeat(chr(0),512);
   if($mode == 2) {
    $cfg['zlib']['write']($fp,$data);
    $cfg['zlib']['close']($fp);
   }
   else {
    fwrite($fp,$data);
    fclose($fp);
   }
  }
  for($a=0;$a<count($list);$a++)			// Liste zusammenstellen
   if(@$var = $list[(($a - $a % 2) / 2) + floor(count($list) % 2 + count($list) / 2) * ($a % 2)])
    $val[$a - $a % 2] = ((isset($val[$a - $a % 2])) ? $val[$a - $a % 2] : '').str_pad($var[0],$mstr[$a % 2]," ")." ".str_pad($var[1],$mlen[$a % 2]," ",STR_PAD_LEFT)." Bytes   ";
  $info = implode("\n",$val);
 }
 else
  dbug("Das zerlegen der Supportdaten ist fehlgeschlagen");
 return $info;
}
function dial($dial,$port=false,$sid=0) {		// Wahlhilfe
 if(!$sid)
  $sid = $GLOBALS['cfg']['sid'];
 $sid = (!is_bool($sid)) ? "&sid=$sid" : '';
 $dial = preg_replace('/[^\d*#]/','',$dial);
 $rdial = urlencode($dial);
 $port = ($port) ? preg_replace('/\D+/','',$port) : false;
 if($GLOBALS['cfg']['fiwa'] >= 530) {
  if($port) {
   dbug("Dial: Ändere Anruf-Telefon auf $port");
   request('POST',"/fon_num/dial_fonbook.lua","clicktodial=on&port=$port&btn_apply=$sid");
  }
  dbug("Dial: ".(($rdial) ? "Wähle $dial" : "Auflegen"));
  request('GET',"/fon_num/fonbook_list.lua",(($rdial == '') ? "hangup=&orig_port=$port" : "dial=$rdial").$sid);
 }
 else {
  request('POST',"/cgi-bin/webcm","telcfg:settings/UseClickToDial=1"
	.(($rdial == '') ? "&telcfg:command/Hangup=" : "&telcfg:command/Dial=$rdial")
	.(($port) ? "&telcfg:settings/DialPort=$port" : "").$sid);
  dbug("Dial: ".(($rdial) ? "Wähle $dial".(($port) ? " für Telefon $port" : "") : "Auflegen"));
 }
 return true;
}
function cfgexport($mode,$pass=false,$sid=0) {		// Konfiguration Exportieren (NUR Exportieren)
 dbug("Exportiere Konfig");
 $body = array('ImportExportPassword' => $pass, 'ConfigExport' => false);
 $path = '/cgi-bin/firmwarecfg';
 if(!$sid) {
  $sid = $GLOBALS['cfg']['sid'];
  if(!is_bool($sid))
   $body = array_merge(array('sid' => $sid),$body);
 }
 return ($mode)	? (($mode === 'array')	? request('POST-array',$path,$body)
					: request('POST-save:'.(($mode === true) ? './' : $mode),$path,$body))
		: request('POST',$path,$body);
}
function cfgcalcsum($data) {				// Checksumme für die Konfiguration berechnen
 if(preg_match_all('/^(\w+)=(\S+)\s*$|^(\*{4}) (?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*(.*?)\3 END OF FILE \3\s*$/sm',$data,$array)) {
  dbug("Berechne Konfig-Checksumme",9);
  dbug($array,4,'CfgCalcSum-#');
  foreach($array[4] as $key => $var)
   $array[0][$key] = ($array[1][$key]) ? $array[1][$key].$array[2][$key]."\0"
	: $array[5][$key]."\0".(($var == 'BIN') ? pack('H*',preg_replace('/[^\da-f]+/i','',$array[6][$key])) : preg_replace('/\r|\\\\(?=\\\\)/','',substr($array[6][$key],0,-1)));
  dbug($array[0],4,'8,CfgCalcSumArray-#');
  dbug(join('',$array[0]),4,'8,CfgCalcSumData');
 }
 return ($array and preg_match('/(?<=^\*{4} END OF EXPORT )[A-Z\d]{8}(?= \*{4}\s*$)/m',$data,$key,PREG_OFFSET_CAPTURE))
	? array($key[0][0],$var = crc_32(join('',$array[0])),substr_replace($data,$var,$key[0][1],8)) : errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function cfgimport($file,$pass='',$mode=false,$sid=0) {	// Konfiguration importieren (Wird vermutlich bald überarbeitet)
 global $cfg;
 if($file and (	is_file($file) and preg_match($cfg['ptar'],$file) and ($data = cfgmake(tar2array($file)))
		or is_file($file) and ($data = file_read($file))
		or is_dir($file) and ($data = cfgmake($file))
	) or !$file and $data = $mode and substr($mode,0,4) == '****') {
  if($mode and $var = cfgcalcsum($data))
   $data = $var[2];
  dbug("Upload Konfig-File an ".$cfg['host']);
  $body = array('ImportExportPassword' => $pass,
	'ConfigImportFile' => array('filename' => $file, 'Content-Type' => 'application/octet-stream', '' => $data),
	'apply' => false);
  if(!$sid) {
   $sid = $cfg['sid'];
   if(!is_bool($sid))
    $body = array_merge(array('sid' => $sid),$body);
  }
  return request('POST','/cgi-bin/firmwarecfg',$body);
 }
 else
  return errmsg('Import-Datei/Ordner nicht gefunden',__FUNCTION__);
}
function cfginfo($data,$mode=0,$file='',$text=false) {	// Konfiguration in Einzeldateien sichern (mode: 0->show, 1->Dir, 2->Tar, 3->tgz)
 global $cfg;
 if(preg_match_all('/^(?:
	\*{4}\s(.*?)\sCONFIGURATION\sEXPORT|(\w+=\S+))\s*$		# 1 Fritzbox-Modell, 2 Variablen
	|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?\r?\n(.*?)\r?\n	# 3 Typ, 4 File, 5 Data
	^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$/msx',$data,$array) and $array[1][0] and $crc = cfgcalcsum($data)) {
  $list = $val = $vars = array();
  $mstr = $mlen = array(0,0);
  dbug($array,4,'CfgInfo-#');
  $fp = ($mode >= 2 and $file) ? (($mode == 2) ? fopen($file,'w') : $cfg['zlib']['open']($file,'w'.$cfg['zlib']['mode'])) : false;	// tar/tgz initialisieren
  foreach($array[3] as $key => $var)			// Config-Dateien aufteilen
   if($var) {
    if($array[3][$key] == 'CFG') {
     $bin = preg_replace('/\r|\\\\(?=\\\\)/','',$array[5][$key]);
     if(!isset($vars['Date']) and preg_match('/^\s\*\s([\s:\w]+)$/m',$bin,$var))
      $vars['Date'] = strtotime($var[1]);
    }
    else
     $bin = pack('H*',preg_replace('/[^\da-f]+/i',"",$array[5][$key]));
    $list[] = array($array[3][$key],$array[4][$key],number_format(strlen($bin),0,",","."));
    if($fp and $mode == 3)
     $cfg['zlib']['write']($fp,data2tar($array[4][$key],$bin,$vars['Date']));
    elseif($fp and $mode >= 2)
     fwrite($fp,data2tar($array[4][$key],$bin,$vars['Date']));
    elseif($mode >= 1)
     file_put_contents($array[4][$key],$bin);
    unset($array[2][$key]);
   }
   elseif($var = ifset($array[2][$key],'/^(\w+)=(.*)$/'))
    $vars[$var[1]] = $var[2];
   else
    unset($array[2][$key]);
  $name = "index.txt";				// Konfig-Schablone sichern
  $data = preg_replace('/^(\*{4}\s(?:CRYPTED)?(?:CFG|BIN)FILE:\S+\s*?\r?\n).*?\r?\n(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?)$/msx','$1$2',$data);
  $list[] = array("TXT",$name,number_format(strlen($data),0,",","."));
  if($fp and $mode == 3)
   $cfg['zlib']['write']($fp,data2tar($name,$data,$vars['Date']));
  elseif($fp and $mode >= 2)
   fwrite($fp,data2tar($name,$data,$vars['Date']));
  elseif($mode >= 1)
   file_put_contents($name,$data);
  if($text) {						// Zugangsdaten sichern
   $name = "zugangsdaten.txt";
   $list[] = array("TXT",$name,number_format(strlen($text),0,",","."));
   if($fp and $mode == 3)
    $cfg['zlib']['write']($fp,data2tar($name,$text,$vars['Date']));
   elseif($fp and $mode >= 2)
    fwrite($fp,data2tar($name,$text,$vars['Date']));
   elseif($mode >= 1)
    file_put_contents($name,$text);
  }
  if($fp) {						// tar/tgz finalisieren
   $data = str_repeat(chr(0),512);
   if($mode == 3) {
    $cfg['zlib']['write']($fp,$data);
    $cfg['zlib']['close']($fp);
   }
   elseif($mode == 2) {
    fwrite($fp,$data);
    fclose($fp);
   }
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
	.implode("\n",$val)."\n".((!$mode and $text) ? $text : '');
 }
 else
  return errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function cfgmake($dir,$mode='',$file=false) {		// Konfiguration wieder zusammensetzen
 if(is_array($dir) and isset($dir[0]) and !$mode and !$file) {		// Helper für Preg_Replace CfgMake
  if(is_array($GLOBALS['val']) and isset($GLOBALS['val'][$dir[3]]))
   $mode = $GLOBALS['val'][$dir[3]];
  elseif(file_exists("$GLOBALS[val]/$dir[3]"))
   $mode = file_get_contents("$GLOBALS[val]/$dir[3]");
  return $dir[1].(($dir[2] == 'BIN') ? wordwrap(strtoupper(implode('',unpack('H*',$mode))),80,$dir[4],1) : str_replace("\\","\\\\",$mode)).$dir[4].$dir[5];
 }
 elseif($dir and (is_array($dir) and $tar = preg_array('/^(index|pattern)\.txt$/',$dir,1) and ($data = $dir[$tar])
		or ($var = glob("$dir/{index,pattern}.txt",GLOB_BRACE)) and ($data = file_get_contents($var[0])))
	and preg_match('/^\*{4}\s+\w+.*CONFIGURATION EXPORT/',$data,$array)) {
  dbug("Setze Konfig-Daten aus ".((is_array($dir)) ? "TAR" : $dir)." zusammen");
  $GLOBALS['val'] = $dir;
  $data = preg_replace_callback('/(^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?(\r?\n))(^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$)/m','cfgmake',$data);
  if(preg_match('/^\*{4}\s(.*?)\sCONFIGURATION\sEXPORT.*?FirmwareVersion=(\S+)/s',$data,$array) and $crc = cfgcalcsum($data)) {
   $val = "Modell:   $array[1]\nFirmware: $array[2]\nChecksum: $crc[0] ";
   $val .= (($crc[0] == $crc[1]) ? "(OK)" : "Inkorrekt! - Korrekt: $crc[1]")."\n";
   $data = ($mode) ? $crc[2] : $data;
   if($file)
    file_write($file,$data);
   return ($file) ? $val : $data;
  }
 }
 return errmsg("Keine Konfig-Daten - Konfig-Schablone nicht gefunden",__FUNCTION__);
}
function konfigdecrypt($data,$pass,$sid=0) {		// Konfig-Datei mit Fritz!Box entschlüsseln
 global $cfg;
 if(!$sid)						// Sid sicherstellen
  $sid = $cfg['sid'];
 dbug($data,4,'12,KonfigDeCrypt-Crypted');
 $plain = $buffer = array();
 if($sid and preg_match_all('/^\s*([\w-]+)\s*=\s*("?)(\${4}\w+)\2;?\s*$/m',$data,$array) and preg_match('/(?:\s*Password\d*=\${4}\w+)+/',$data,$var)) {
  if(preg_match('/^boxusers\s\{\s*^\s{8}users(\s\{.*?^\s{8}\}$)/smx',$k = str_replace("\t","        ",$cfg['zlib']['inflate'](base64_decode("
	tVnrbxs3Ev8s/RU6+ZuBxJIjO01SH9A4dupD/EDspL1DAYJaUrusd0keyZWsBvnfb4aPfchOIvdgx4B3Z4bkcB6/mdns7u7ujk4/nt385x9v1d3o5ezVZHR8
	eXF69v7Tx19uzi4vRie/X11+vNmZDE+FqVbU8M/cWKHk0XT64vnk8PnBZBgWkLOL65tfPnwgN/++OjmqhLYvZuRgun/+ltyJUsg7smS2JIxnbjY7JLOcu4Ls
	0zkRlkkiHXGcaOUs2a/tnBTKOrIqqZxOJdlHvYaXJ+dHdFkNj1UtnVkfTWavhh+ozGua8yPGh7t4l+PT96dnH05eU/PyebbIh3u7w9HuaG9JzZ6rtCfB+01R
	j/5F5Wgyhd/Xkwn8jqavXk6AtzccVtzR0ZcRl5liQuajo9G4dotnP43fjL4Oh7Az7DL6MhxUinFgwq0YPhKjasfNm+GAZk4s4TZGLQXjBjcQEliSu3HLlrTC
	5WMkiZwxLum85AxIa24jjWQK7qpK0jKlAt6KSjI3guWcrATYkRWZzpqVHW5OHV/RNbAmz/0/YHthUltOrKNOZIRJmzZGHoEbLZSpK2XyRAdnxevGp3gAsFym
	KuKoAYdSkxWNFt7dhlta8kj3O1G40ZJ0KXOZk97KwgqvHt5jCTHQYyAhOuAr+MDVJKvdnGa3JCoIJK8grR3u3pEA5vRggia4xotbuDs8GHe5OAel8PwpXtWb
	mlQ0I2rJDVyTNwoAEW/Vofvg6f6i5/TyMCqDj16TCi0deLOWNyNSmYqWwLDcCFq2NwM7+6BZ0IzbSAObb5JAnYb2AIlAMBuBobHvDw9ei4Jaa8UXq7R5DRwX
	X5ZZlk6oslpbZzitNgN5qYQmECkAC8zUJbcpnD1D6MMHmc5MDl99h/OtdelkIgAuqCOeSe5lTZKCZNxG7KGT0IBdG4fg9m8Dt9boOzAduCqGPUm7wdpBSuuu
	oQZKlmuSmbV2GAxN5A9KldHS7zuAgDdp7c7Urxpoau2KdSigzMDwSjket4l64BtqRrKC6jc9IeLPVhKUhEQNeBZsEIX88bixkB6ZhFsTJyoOBgTRgwol558s
	Py7wsmd4rSUona4g79GnfkmZaZ4VijBhAcQkwH5K0MSJCQpGyAWYoSPoMU0Xa4v0Vt+ORKvgZIWsBSjf2QGWtRoKDW6YC2WtVmoRLBA4AHwLUYLiGAYk4jIT
	BnZQZt1eUWE05QqWEty5QctBpqxqvYBvXsBXgQE+eWBivPQQPPW3ENk8GiIuiwSAAsl9sGwKqA2BymZzbfiCm7YiDKq6dFhnbxt/FpxC9QGtKhC2WLQ7pqSO
	bnDiYchBRuMeKBIZ4jiPfikAOME6RNbV3Be3cD6US4zv3l4h4pPanfiCY5dconDInRA4wZWSrwADKGMGozY6tL3mOjJ7JW0ASj1IR6ReGQG9Bfh6+h3efp83
	/6zKuuIfoddgn3Rzeo/6du08Zkwn/scvu4o1/10TilfNVZtdvifUzSP2I+FfVY33nf3waBS85q71PyKLWKKBU2S3FoZQO8Q6in+JWiwScRaJnaIVqk1eqjmU
	LiUXIlUvmgF8AoxCF7cMDWPUM7RPHeL0BVC7KNzlIVIjEoe9u5yDVC980gMjpm9zxT4zJpTHQuxx7glgaGPJQKbSXXf15UDTrM26Pg9bEygJtxzfvi3S1nhs
	chbijjPf6pK85mAv0YRbraWGBDCWAJKoFVFG5EI2ZWqTHfL9m2zoBArFHmBX9I5AD50iLkgjcOUGAx1hogqpX1FZgztcbfz9gqGDfGYAXwmDPkdIv6LhR9/x
	O6yHILzGIiXz1oilqAQgum88iYGGta3S2vfUJNjJgHGgjcoKxGHPAMENcgQtb0lLcFCorWvrPvTvQ6iz4IngBHxf8bk1y/iC/Vp8hODVrRTGRVjZIWSlgPxK
	BGn7AthWf/GpgBNLaB82+5BBsJgv8fu+xPf6gBfjlKyxDQgE3Lo7XfyMixiH2sbZP8chL7Ed6LUs445QOMkgyowL5/TrvT3fiuDYFdc3+3/pNDX3Dvrm5iXk
	Yqp7M98QOFVnBdbtBumjuWJAgrcPNylyofpaNtdPwxc8kuXMqxydWQtUuWtGXORtqAxL7/xOc+PiLnFsEn8BBvmqWHLXxqcHURIaBdQdSNANMxIwrkEObGMa
	0MiUuhX+8Olk+iz+TLyHqdab45yfEgqBM2CHNLtPgs549RAVGl1sXfocyKYSq1ufioW/TwHoCWOBBQQSG0zfyu9LdvX5eIOThTn8gTUpdDz8a8RsyEGJ1Wdc
	cSbq6giKBprC3gq0hW+UVx0PhZMbsB9P91/MDv74Q45DBmdUC0RTgIyYaxySqJTKicUanX+/21+oljZN/X8osxXOfuUaiKdG+RlnZ4bK3aj2+fr85uq6ic0m
	VV7vH4SB3tuiydpZE3CsfbcFoKQPJTRLo5ono5FxFu9TbwWz96lQwiDJMgCUe6yFkn0iDrfOt6uBgLOrrm2B1kolcdKOvDAhgNsg1m0Nk6Fx2Ao2SRBE/Ja1
	fkgiM9QWhiM57e1UEvRTyIZQNz3DpyEHejXKxhOb4SHwYui1NBL8NPZZiU2qXrF7497pb5806MpxpI6197jbrvwGOfAeawZ+GKjT3HepubwCTdP7BV+940vR
	DNrvBM2lsun1mmc1tJPrc9Q0kD5Q684BJKC8njXQs1jVXhnrzyIRQXnQ8JO8lWol0XutieE+UDLbvgNDXoq8CHNQ+kgUc0G6fl1CO4QnFP4LmnFfx2mSr8J3
	q/hmRMqpTNfEZgVn0JRF0s1HMjmcpX2pZN4aNhK0gRkrKfHf5hG/83l/Nw1ipIYBHHq7SKoBxQAnGLRDwUCRTpcVYGu6hNWcMzRec2oAGwifBYUZKJL/pFZJ
	IR5CAywNhMd4Gu/4FPa0bo0I5Lm6I8K/w9PEw9Dhy59eTeg8g+OetZRW2O+iC4hz3u7D1hKHjCbk46Hxuwqxa5kBUErW6cOxkyG4peE54qfp+B7IqLBNhe4b
	vYXXfOew+1Vi56WvoM3t2/6iUxsHSy1jdWumAtSEsgqaO4OBhwwLrpv/mcpgIDfDnqR2S0ldYEh+XxbrbRjVFoDRpFAVh9gBnW8TsAyQ5m32/+70dRg/jKSV
	3ozTSa8kYQ2BVQS7Yfx0gB9aNuzvg1OtsAzKFMpoFcir+FYoZ7VybZNZ7rtOywmNQsqhkA/+e23aqgqwYsM9ep15ENjbG51cng7Dd/GTi3ejy9MRfhsfIaH/
	tRynjyf6XL5qBp5H6oQ18Yl0wq3/lk61nT+RSrAzNhTtSMLosofi2DjRJhpwdCtVDc0qNHk9XIWi2JHMdZXWK6ML/OJ9myYdGeaxRQkVuQ1PNn+8WfyI+0SG
	8Xs/yllvzy68Vos7kuVbS4INt5UF5N9WtMzMVqKOlxy6N1IJm221wIPmHIaMraTxSySU0K1kmW+sttUDlM6cKbffmXMo2NV28lVuCPQ9DLs6LHPb3dVnw3ZW
	p9UP/d7AkX4qhISd/yYYcfNkaNT9QPKInKsrZ70C23uKbXVZWtDamq23BvHHyGJdfYw8RvJj5KHDeIx4+J76mBVpqPvhmkgN/6s/msSfIPA/"))),$v))
   $export = array(str_replace("#0",$var[0],$k),$v[1]);	// Stark gekürze und leere Fritz!Box 7490 Konfig
  if(preg_match_all('/\${4}\w+/',$var[0],$val))		// Salt-Kennwörter entfernen
   foreach($val[0] as $var)
    if(($key = array_search($var,$array[3])) !== false) {
     $plain[$var] = $pass;
     unset($array[3][$key]);
    }
  if(preg_match_all('/^\*\*\*\*\sBINFILE:(.*)\s*([\dA-F\s]*?(?:24\s*){4}(?:[46][1-9A-F]|[57][0-9A]|3\d|\s*)+[\dA-F\s]*)/mi',$data,$binfile)) {
   $binfile = array_combine($binfile[1],$binfile[2]);	// Binfiles mit verschlüsselten Werten einbeziehen
   foreach($binfile as $key => $var) {
    $binfile[$key] = pack('H*',preg_replace('/[^\da-f]+/i',"",$var));
    if(preg_match_all('/\${4}\w+/',$binfile[$key],$val))
     $array[3] = array_merge($array[3],$val[0]);
   }
  }
  else
   $binfile = false;
  dbug($array,4,'KonfigDeCrypt');
  $list = array_unique($array[3]);			// Doppelte Einträge entfernen
  shuffle($list);					// Alle Einträge durchwürfeln (Für Problemfälle)
  if(preg_match_all('/\${4}\w+/',$var[0],$array))	// Salt-Kennwörter eintragen
   foreach($array[0] as $var)
    $plain[$var] = $pass;
  $dupe = array(array(1,4,'/^(.*?)$/'), array(15,5,'/(?<=^|,\s)(.*?)(?=,\s|$)/'));
  $pregimport = array(
   'de' => array('Internetzugangsdaten' => array(1,0,'/^Benutzer:\s(.*?)(?:,\sAnbieter:\s.*?)?$/'), 'Dyn(amic )?DNS' => array(2,1,'/(?<=Domainname:\s|Benutzername:\s)(.*?)(?=,\s|$)/'),
    'PushService' => array(1,3,'/^E-Mail-Empfänger:\s(.*?)$/'), 'MyFRITZ!' => $dupe[0], 'FRITZ!Box-Benutzer' => $dupe[1]),
   'en' => array('Internet Account Information' => array(1,0,'/^User:\s(.*?)(?:,\sProvider:\s.*?)?$/'), 'Dyn(amic )?DNS' => array(2,1,'/(?<=Domain\sname:\s|user\sname:\s)(.*?)(?=,\s|$)/'),
    'Push service' => array(1,3,'/^e-mail\srecipient:\s(.*?)$/'), 'MyFRITZ!' => $dupe[0], 'FRITZ!Box Users' => $dupe[1]),
   'es' => array('Datos de acceso a Internet' => array(1,0,'/^Usuario:\s(.*?)(?:,\sProvider:\s.*?)?$/'),
    '(Dyn)?DNS( dinámico)?' => array(2,1,'/(?<=Nombre\sdel\sdominio:\s|nombre\sdel\susuario:\s)(.*?)(?=,\s|$)/'),
    'Push Service|Notificaciones' => array(1,3,'/^Destinatario\sde\scorreo:\s(.*?)$/'), 'MyFRITZ!' => $dupe[0], 'Usuarios de FRITZ!Box' => $dupe[1]),
   'fr' => array('Données d\'accès à Internet' => array(1,0,'/^Utilisateur[\xa0\s]?:[\xa0\s](.*?)(?:,\sProvider:\s.*?)?$/'),
    '(Dyn)?DNS( dynamique)?' => array(2,1,'/(?<=Nom\sde\sdomaine[\xa0\s]:[\xa0\s]|nom\sd\'utilisateur[\xa0\s]:[\xa0\s])(.*?)(?=,\s|$)/'),
    'Service push' => array(1,3,'/^Destinataire\sdu\scourrier\sélectronique[\xa0\s]?:[\xa0\s](.*?)$/'), 'MyFRITZ!' => $dupe[0], 'Utilisateur de FRITZ!Box' => $dupe[1]),
   'it' => array('Dati di accesso a Internet' => array(1,0,'/^Utente:\s(.*?)(?:,\sProvider:\s.*?)?$/'), 'Dyn(amic )?DNS' => array(2,1,'/(?<=Nome\sdi\sdominio:\s|nome\sutente:\s)(.*?)(?=,\s|$)/'),
    'Servizio Push' => array(1,3,'/^Destinatario\se-mail:\s(.*?)$/'), 'MyFRITZ!' => $dupe[0], 'Utenti FRITZ!Box' => $dupe[1]),
   'pl' => array('Dane dost\?powe do internetu' => array(1,0,'/^U\?ytkownik:\s(.*?)(?:,\sProvider:\s.*?)?$/'),
    'Dyn(amic )?DNS' => array(2,1,'/(?<=Nazwa\sdomeny:\s|nazwa\su\?ytkownika:\s)(.*?)(?=,\s|$)/'), 'Push Service' => array(1,3,'/^Odbiorca\se-maila:\s(.*?)$/'),
    'MyFRITZ!' => $dupe[0], 'U\?ytkownicy FRITZ!Box' => $dupe[1]),
  );
  $lang = (ifset($cfg['boxinfo']['Lang']) and ifset($pregimport[$cfg['boxinfo']['Lang']])) ? $cfg['boxinfo']['Lang'] : 'de';
  dbug((count($list) + count($plain))." verschiedene verschlüsselte Einträge gefunden! (Sprachmuster: $lang)");
  dbug($list,4,'KonfigDeCrypt-List');
  $b = 1;
  while($list) {					// Alle Verschlüsselte Einträge durchlaufen
   $import = $export[0];
   $buffer = array_values($buffer);
   while($list and count($buffer) < ((ifset($cfg['boxinfo']['Name'],'/FRITZ!Box 7312/i')) ? 4 : 20))	// Die ersten 20 Einträge sichern
    $buffer[] = array_shift($list);
   $a = 0;						// Import-Buffer füllen
   $v = array();
   foreach($buffer as $var)
    if($a++ < 5)
     $import = str_replace("#$a",$var,$import);
    else
     $v[] = str_replace('#7',$var,str_replace('#6',4+$a,$export[1]));
   $import = preg_replace('/(^boxusers\s\{\s*^\s{8}users)\s\{.*?^\s{8}\}$/smx',"$1".str_replace('$','\$',implode('',$v)),$import);
   if($var = cfgcalcsum($import))			// Checksum berechnen
    $import = $var[2];
   dbug($import,4,"KonfigDeCrypt-Import-$b");
   dbug($buffer,4,"KonfigDeCrypt-Buffer-".($b++));
   if($var = request('POST','/cgi-bin/firmwarecfg',array('sid' => $sid, 'ImportExportPassword' => $pass,
	'ConfigTakeOverImportFile' => array('filename' => 'fritzbox.export', 'Content-Type' => 'application/octet-stream', '' => $import), 'apply' => false)) and !preg_match('/cfg_nok/',$var)) {
    if($getdata = utf8_decode(($cfg['fiwa'] < 650) ? request('GET',"/system/cfgtakeover_edit.lua?sid=$sid&cfg_ok=1") : request('POST',"/data.lua","xhr=1&sid=$sid&lang=de&no_sidrenew=&page=cfgtakeover_edit"))) {
     if(preg_match_all('/^\s*\["add\d+_text"\]\s*=\s*"([^"]+)",\s*$.*?^\s*\["gui_text"\]\s*=\s*"([^"]+)",\s*$/sm',$getdata,$match))
      $match[2] = array_flip($match[2]);
     elseif(preg_match_all('/<label for="uiCheckcfgtakeover\d+">(.*?)\s*<\/label>\s*<span class="addtext">(.*?)\s*<br>\s*<\/span>/',$getdata,$match))
      $match = array(1 => $match[2], 2 => array_flip($match[1]));
     if($match) {					// Decodierte Kennwörter gefunden
      dbug($match,4,'KonfigDeCrypt-Match');
      foreach($pregimport[$lang] as $key => $var)
       if($k = preg_array("/^$key$/",$match[2],1) and preg_match_all($var[2],$match[1][$match[2][$k]],$array))
        foreach($array[1] as $k => $v)
         if(isset($buffer[$var[1] + $k]) and $buffer[$var[1] + $k] != $v) {	// Kennwort sichern
          $plain[$buffer[$var[1] + $k]] = str_replace('"','\\\\"',html_entity_decode($v,ENT_QUOTES,'ISO-8859-1'));
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
    return errmsg('Entschlüsselungsversuch wurde nicht akzeptiert'
	.(($val = ifset($var,'/<p class="ErrorMsg">(.*?)<\/p>/')) ? (($val[1] == '?1043?')
	? "\nBitte \"Ausführung bestimmter Einstellungen und Funktionen zusätzlich bestätigen\" deaktivieren!" : " - $val[1]") : ''),__FUNCTION__);
   dbug((count($list)) ? floor(count($plain)/(count($list)+count($plain))*100)."% entschlüsselt..." : "100% ".(($buffer) ? "Es konnte".((count($buffer) == 1)
	? " ein Eintrag" : "n ".count($buffer)." Einträge")." nicht Entschlüsselt werden! " : "")."- Ersetze ".count($plain)." entschlüsselte Einträge...");
  }
  dbug($buffer,4,'KonfigDeCrypt-Resistant');
  dbug($plain,4,'KonfigDeCrypt-Plain');			// Plaintext sichern
  if($binfile)						// BinFiles wieder zusammenpacken
   foreach($binfile as $key => $var)
    $data = preg_replace("/(?<=\*{4} BINFILE:$key\s)[\dA-F\s]*(?=\s\*{4} END OF FILE \*{4})/",
	wordwrap(strtoupper(implode('',unpack('H*',str_replace(array_keys($plain),array_values($plain),$var)))),80,"\n",1),$data,1);
  $data = str_replace(array_keys($plain),array_values($plain),$data);
  dbug($data,4,'12,KonfigDeCrypt-Decrypted');
  return $data;
 }
 return errmsg('Keine Konfig-Datei',__FUNCTION__);
}
function konfig2array($data) {				// FRITZ!Box-Konfig -> Array
 $config = array();
 if($data{0} == '*')
  dbug("Konvertiere Fritz!Konfig...");
 if($data{0} == '*' and preg_match_all('/^(?:\*{4}\s(.*?)\sCONFIGURATION\sEXPORT|(\w+)=(\S+))\s*$
	|^\*{4}\s(?:CRYPTED)?(CFG|BIN)FILE:(\S+)\s*?\r?\n(.*?)\r?\n^\*{4}\sEND\sOF\sFILE\s\*{4}\s*?$/msx',$data,$array)) {
  dbug($array,4,'Konfig2Array-#');			// Debugdaten Speichern
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
  dbug($config,4,'Konfig2Array');			// Debugdaten Speichern
 }
 elseif($data{0} == '{' and preg_match_all('/\{\s*?$.*?^\}/msx',$data,$array)) {
  dbug($array,4,'Konfig2Array-Multi-#');		// Debugdaten Speichern
  if(count($array[0]) > 1)				// Ein oder Multi-Array
   foreach($array[0] as $var)				// Weitere Matches auf selber Ebene
    $config[] = konfig2array($var);
  elseif(preg_match_all('/^\s{8}(?:(\w+)\s(?:=\s(?:([^\s"]+)|(".*?(?<!\\\\)"(?:,\s*)?));|(\{\s*$.*?^\s{8}\}))\s*$)$/msx',$data,$match)) {
   dbug($match,4,'Konfig2Array-Sub-#');			// Debugdaten Speichern
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
 if($konfig = konfig2array($data)) {			// Konfig als Array umwandeln
  dbug("Stelle Zugangsdaten zusammen...");
  $access = array(
   'Mobile-Stick'		=> array(&$konfig['ar7cfg']['serialcfg'],'=number,provider,username,passwd'),
   'DSL'			=> array(&$konfig['ar7cfg']['targets'],'-name,>local>username,>local>passwd'),
   'IPv6'			=> array(&$konfig['ipv6']['sixxs'],'=ticserver,username,passwd,tunnelid'),
   'DynamicDNS'			=> array(&$konfig['ddns']['accounts'],'=domain,username,passwd'),
   'MyFRITZ!'			=> array(&$konfig['jasonii'],'=user_email,user_password,box_id,box_id_passphrase,dyn_dns_name'),
   'FRITZ!Box-Oberfläche'	=> array(&$konfig['webui'],'=username,password'),
   'Fernwartung'		=> array(&$konfig['websrv']['users'],'=username,passwd'),
   'TR-069-Fernkonfiguration'	=> array(&$konfig['tr069cfg']['igd']['managementserver'],'=url,username,password,ConnectionRequestUsername,ConnectionRequestPassword'),
   'Telekom-Mediencenter'	=> array(&$konfig['t_media'],'=refreshtoken,accesstoken'),
   'Google-Play-Music'		=> array(&$konfig['gpm'],'=emailaddress,password,partition,servername'),
   'Onlinespeicher'		=> array(&$konfig['webdavclient'],'=host_url,username,password'),
   'WLAN'			=> array(&$konfig['wlancfg'],'/^(((guest|sta)_)?(ssid(_scnd)?|pskvalue)|(sta_)?key_value\d|wps_pin|wds_key)$/i'),
   'Push-Dienst'		=> array(&$konfig['emailnotify'],'=From,To,SMTPServer,accountname,passwd','+To,arg0'),
   'DECT-eMail'			=> array(&$konfig['configd'],'!<\?xml.*?<list>.*?<email>.*?<pool>(.*?)</pool>!s','!((name)|user_name|(?:smtp_)?server|user|pass|uipin|port)="([^"]+)"!s'),
   'FRITZ!Box-Benutzer'		=> array(&$konfig['boxusers']['users'],'-name,email,passwd,password'),
   'InternetTelefonie'		=> array(&$konfig['voipcfg'],'_name,username,authname,passwd,registrar,stunserver,stunserverport,gui_readonly'),
   'IP-Telefon'			=> array(&$konfig['voipcfg']['extensions'],'-extension_number,username,authname,passwd,clientid'),
   'Online-Telefonbuch'		=> array(&$konfig['voipcfg']['onlinetel'],'-pbname,url,serviceid,username,passwd,refreshtoken,accesstoken'),
   'Virtual-Privat-Network'	=> array(&$konfig['vpncfg']['connections'],'-name,>localid<fqdn,>remoteid<fqdn,>localid<user_fqdn,>remoteid<user_fqdn,key,>xauth>username,>xauth>passwd'),
  );
  foreach($access as $key => $var)		// Accessliste durcharbeiten
   if(ifset($var[0])) {
    if($var[1]{0} == '/') {			// Reguläre Ausdrücke verwenden (Schlüsselname)
     foreach($var[0] as $k => $v)
      if(preg_match($var[1],$k) and $var[0][$k])// Schlüssel Suchen und Prüfen
       $config[$key][$k] = $var[0][$k];
    }
    elseif($var[1]{0} == '!' and preg_match($var[1],$var[0],$val) and preg_match_all($var[2],$val[1],$val)) {	// Reguläre Ausdrücke verwenden (Inhalt)
     foreach($val[3] as $k => $v)
      if(ifset($val[2][$k]))
       $name = $v;
      else
       $config[$key][$name][$val[1][$k]] = $v;
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
          $config[$key][$name][(($va1[2] == '<') ? $va1[1] : $va1[3])] = $var[0][$k][$va1[1]][$va1[3]];	// Den Vorigen Schlüssel verwenden?
        elseif(ifset($var[0][$k][$val]))		// Auf der neuen Ebene Prüfen
         if($name === false)
          $name = (string)$var[0][$k][$val];
         else
          $config[$key][$name][$val] = $var[0][$k][$val];
      }
    }
   }
  dbug($config,4,'ShowAccessData');			// Alle Fundstücke ungefiltert sichern
  if(ifset($config['InternetTelefonie']))		// Filter: StunServerPort 3478 filtern
   foreach($config['InternetTelefonie'] as $key => $var)
    if(ifset($var['stunserverport'],3478))
     unset($config['InternetTelefonie'][$key]['stunserverport']);
  if(ifset($config['IPv6']) and ifset($config['IPv6']['ticserver']) and count($config['IPv6']) == 1)	// Filter: IPv6 tivserver
   unset($config['IPv6']);
  if(ifset($config['TR-069-Fernkonfiguration']) and ifset($config['TR-069-Fernkonfiguration']['url']) and count($config['TR-069-Fernkonfiguration']) == 1)	// Filter: TR069 url
   unset($config['TR-069-Fernkonfiguration']);
  if(ifset($config['Mobile-Stick']) and ifset($config['Mobile-Stick']['username'],'ppp') and ifset($config['Mobile-Stick']['passwd'],'ppp'))	// Filter: Surf-Stick ppp
   unset($config['Mobile-Stick']);
  if(ifset($config['DECT-eMail']))									// Filter: DECT-eMail
   foreach($config['DECT-eMail'] as $key => $var) {
    if(ifset($var['server']) and ifset($var['port'])) {	// Server & Port zusammenführen
     $config['DECT-eMail'][$key]['server'] .= ":$var[port]";
     unset($config['DECT-eMail'][$key]['port']);
    }
    if(ifset($var['user'],$key))			// Doppelte Namen
     unset($config['DECT-eMail'][$key]['user']);
    if(ifset($var['user_name'],$key))
     unset($config['DECT-eMail'][$key]['user_name']);
   }
  $a = array('/^\${4}\w+/','(Verschlüsselt)');
  foreach($config as $key => $var)									// Verschlüsselte Einträge umschreiben
   if($var) {
    if($val = preg_array($a[0],$var,3)) {		// Array-Schlüssel umbennenen
     foreach($val as $k => $v) {
      $config[$key][] = $v;
      unset($config[$key][$k]);
     }
     $var = $config[$key];
    }
    foreach($var as $k => $v)
     if(is_array($v)) {				// Unterwert umschreiben
      foreach($v as $kk => $vv)
       if(ifset($vv,$a[0]))
        $config[$key][$k][$kk] = $a[1];
     }
     elseif(ifset($v,$a[0]))			// Hauptwert umschreiben
      $config[$key][$k] = $a[1];
   }
  foreach($config as $key => $var) {									// Array in Text Umwandeln
   if($var and count($var))
    $text .= "\n$key\n";
   if($var and $kl = max(array_map('strlen',array_keys($var))))
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
 else
  dbug("showaccessdata fehlgeschlagen");
 return $text;
}
function getevent($filter='aus',$sep="\t",$sid=0) {	// Ereignisse abrufen
 global $cfg;
 $filters = array('aus','system','internet','telefon','wlan','usb');
 $filter = (($var = ifset($filters,$filter)) !== false) ? $var : 0;
 dbug("Hole Ereignisse (Filter: {$filters[$filter]})");
 if(!$sid)
  $sid = $cfg['sid'];
 $sid = (!is_bool($sid)) ? "&sid=$sid" : '';
 if($cfg['fiwa'] < 500)
  $data = request('POST-array','/cgi-bin/webcm',"getpage=../html/de/system/ppSyslog.html&logger:settings/filter=$filter$sid");
 elseif($cfg['fiwa'] < 669)
  $data = request('GET-array',"/system/syslog.lua?tab={$filters[$filter]}&event_filter=$filter&stylemode=print$sid");
 if(ifset($data) and preg_match_all(($cfg['fiwa'] < 500) ? '!<p class="log">(\S*)\s*(\S*)\s*(.*?)</p>!'
	: '!<tr><td[^>]*>(?:<div>)?(.*?)(?:</div>)?</td><td[^>]*>(.*?)</td><td[^>]*><a[^>]*>(.*?)</a></td></tr>!',$data[1],$array)) {
  dbug($array,4,'GetEvent');
  foreach($array[1] as $key => $var)
   $array[0][$key] = $array[1][$key].$sep.$array[2][$key].$sep.$array[3][$key];
  $array = implode("\r\n",array_reverse($array[0]))."\r\n";
  if(ifset($data['Content-type'],'/utf-?8/'))
   $array = utf8_decode($array);
  return html_entity_decode($array,ENT_QUOTES,'ISO-8859-1');
 }
 elseif($cfg['fiwa'] >= 669 and $data = request('POST-array','/data.lua',"xhr=1$sid&lang=de&page=log&xhrId=$filter&no_sidrenew=")
	and preg_match('/"log":\[((?:[^\[\]]++|\[(?1)\])*)\]/',$data[1],$array))
  return utf8_decode(implode("\r\n",array_reverse(explode("\n",preg_replace(
	array('/\["(.*?)(?<!\\\\)","(.*?)(?<!\\\\)","(.*?)(?<!\\\\)"(,".*?(?<!\\\\)"){3}\],?/','!\\\\(?="|/)!'),
	array("$1 $2 $3\n",""),$array[1])))));
 else {
  if(ifset($data))
   dbug($data,4,'GetEvent-data');
  if(ifset($array))
   dbug($array,4,'GetEvent_array');
  return errmsg("Keine Ereignisse bekommen".(($var = errmsg(0,'request')) ? " ($var)" : ""),__FUNCTION__);
 }
}

# Eigentlicher Programmstart

if(ifset($argv) and $argc and (float)phpversion() > 4.3 and $ver = ifset($ver,'/^(\w+) ([\d.]+) \(c\) (\d\d)\.(\d\d)\.(\d{4}) by ([\w ]+?) <([.:\/\w]+)>$/')) { ## CLI-Modus ##
 $ver[] = intval($ver[5].$ver[4].$ver[3]);		// fb_tools Datum (8)
 $ver[] = floatval($ver[2]);				// fb_tools Version (9)
 $php = array(phpversion(),php_uname(),php_sapi_name());// PHP Infos (0 -> version, 1 -> uname, 2 -> sapi_name)
 $uplink = array("mengelke.de","/Projekte;$ver[1].");	// Update-Link
 if(!$script = realpath($argv[0]))			// Pfad zum Scipt anlegen
  $script = realpath($argv[0].".bat");			// Workaround für den Windows-Sonderfall
 $self = basename($script);				// Script_Name
 $cfg['ptar'] = '/\.t(?=\w)(?:ar)?(\.?gz)?$/i';		// Tar-Ausdruck
 $cfg['dbcd'] = realpath('.').'/';			// Current_Dir für Debug-Daten
 $cfg['head'] = array('User-Agent' => "$self $ver[2] $php[1] PHP $php[0]/$php[2]");	// Fake UserAgent
 $ext = strtolower(preg_replace('/\W+/','',pathinfo($script,PATHINFO_EXTENSION))); // Extension für Unix/Win32 unterscheidung
 define($ver[1],1);					// Feste Kennung für Plugins etc.
 if(!preg_match('/cli/',$php[2]) and function_exists('header_remove')) {	// HTTP-Header löschen wenn PHP-CGI eingesetzt wird
  header('Content-type:');
  header_remove('Content-type');
  header_remove('X-Powered-By');
 }
 foreach(array(".",$script) as $var) {			// Benutzerkonfig suchen
  $var = realpath($var);
  if(is_file($var))
   $var = dirname($var);
  if(is_dir($var))
   $var .= "/".basename($cfg['usrcfg']);
  if(file_exists($var)) {				// Benutzerkonfig gefunden und laden
   dbug("Lade Benutzer-Konfig: $var");			// Debug-Meldung (dbug muss im Haupt-Quelltext aktiviert werden)
   include $var;
   break;
  }
 }
 if(@ini_get('pcre.backtrack_limit') < $cfg['pcre']) 	// Bug ab PHP 5 beheben (Für Große RegEx-Ergebnisse)
  @ini_set('pcre.backtrack_limit',$cfg['pcre']);
 if($cfg['time'])					// Zeitzone festlegen
  @ini_set('date.timezone',$cfg['time']);
 $cfg['time'] = (ifset($_SERVER['REQUEST_TIME_FLOAT'])) ? $_SERVER['REQUEST_TIME_FLOAT'] : array_sum(explode(' ',microtime()));	// Startzeit sichern
 $gz = (function_exists("gzopen") or function_exists("gzopen64")) ? true : false;	// ZLib Funktionen initialisieren
 foreach(explode(',','open,close,eof,file,read,write,Encode,Decode,Deflate,Inflate') as $key)
  $cfg['zlib'][strtolower($key)] = ($key == strtolower($key)) ? (($gz and function_exists("gz$key")) ? "gz$key" : (($gz and function_exists("gz$key64")) ? "gz$key64" : $key)) : strtolower("gz$key");
 $pmax = $argc;		// Anzahl der Parameter
 $pset = 1;		// Optionszähler

# Drag'n'Drop Modus
 if(ifset($cfg['drag']) and $pset+1 == $pmax and file_exists($argv[$pset])) {
  dbug("Nutze Drag-Parameter: $cfg[drag]");	// Debug-Meldung (dbug muss entweder im Haupt-Quelltext oder in der Config-Datei aktiviert werden)
  $drag = explode(',',$cfg['drag']);
  array_splice($argv,$pmax,0,explode(' ',$drag[1]));
  array_splice($argv,$pset,0,explode(' ',$drag[0]));
  $pmax = $argc = count($argv);
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
  if(ifset($array[1]))
   $cfg['user'] = $array[1];
  if(ifset($array[2]))
   $cfg['pass'] = $array[2];
  if(ifset($array[4]))
   $cfg['port'] = $array[4];
  $pset++;
 }
 unset($cfg['preset']);					// Preset-Daten werden nicht mehr benötigt!

# Optionen setzen
 while($argv[$pmax-1]{0} == '-' and ($pmax == $argc and !ifset($argv[$pmax-2],'/^-/')
  and preg_match_all('/-([a-z]+)(?:[:_=]([(\/\w%#~;,.!+)]+))?(?=-|$)/i',$argv[$pmax-1],$array) or preg_match('/^-(\w+)(?:[:_=](.+))?$/',$argv[$pmax-1],$array))) {
  if(is_string($array[0]))
   $array = array(array($array[0]),array($array[1]),array($array[2]));
  $pmax--;
  foreach($array[1] as $key => $var) {
   $vas = $array[2][$key];
   if($var == 'h')	// Help
    $cfg['help'] = ($vas) ? $vas : true;
   if($var == 'd') {	// Debug
    $cfg['dbug'] = ($val = ifset($vas,'/^(\d+)(?:.(.+))?$/')) ? intval($val[1]) : true;
    if(ifset($val[2]) and (file_exists($val[2]) and is_dir($val[2]) or !file_exists($val[2]) and $val[2] = makedir($val[2],0)))
     $cfg['dbcd'] = realpath($val[2]).'/';	// CD Setzen
   }
   if($var == 'w')	// Wrap
    $cfg['wrap'] = (ifset($vas,'/^[1-9]\d+$/')) ? intval($vas) : 80;
   if($var == 'c')	// Char
    $cfg['char'] = strtolower($vas);
   if($var == 't')	// Timeout
    $cfg['tout'] = (ifset($vas,'/^\d+$/')) ? intval($vas) : 0;
   if($var == 'b')	// Buffer
    $cfg['sbuf'] = (ifset($vas,'/^[1-9]\d{2,}$/') ) ? intval($vas) : 4096;
   if($var == 's') {	// SID
    if(preg_match('/^[\da-f]{16}$/i',$vas))
     $cfg['bsid'] = $cfg['sid'] = $vas;
    elseif(file_exists($vas) and preg_match('/(?<=^|\W)[\da-f]{16}$/i',file_get_contents($vas),$val))
     $cfg['bsid'] = $cfg['sid'] = $val[0];
    if($cfg['bsid'])
     dbug("Recycle Login-SID: $cfg[host]");
   }
   if($var == 'gz')	// GZip Crunchlevel
    $cfg['zlib']['mode'] = ($v = ifset($vas,'/^-?\d[fhR]?$/')) ? $v[0] : -1;
   if($var == 'fw' and $v = ifset($vas,'/^(\d+\.0)?([1-9])\.?(\d{2})(-\d+)?$/'))	// Fritz!Box Firmware-Version
    $cfg['fiwa'] = (int)$v[2].$v[3];
   foreach(array('o' => 'oput', 'un' => 'user', 'pw' => 'pass', 'fb' => 'host', 'pt' => 'port') as $k => $v)	// Optionen mit Zwangsparameter ohne Prüfung
    if($var == $k and $vas)
     $cfg[$v] = $vas;
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

# Consolen Breite automatisch ermitteln
 if($cfg['wrap'] == 'auto') {
  if(isset($_SERVER['HOME']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL']))	// Unix/Linux/Mac
   $cfg['wrap'] = (file_exists('/usr/bin/tput') and $a = (int)@exec('tput cols')
	or file_exists('/bin/stty') and $a = (int)preg_replace('/^\d+\D*/','',@exec('stty size'))) ? $a : 0;
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']) and (@exec('mode con',$var) or true)	// Windows
	and is_array($var) and preg_match_all('/(?:(zeilen|lines)|(spalten|columns)|(code\s?page)):\s*(\S+)/',strtolower(implode('',$var)),$val))
   foreach($val[4] as $key => $var) {
    if(ifset($val[2][$key]))	// Breite sichern
     $cfg['wrap'] = $var;
    if(ifset($val[3][$key]))	// Codepage merken
     $char = "cp".(($var == 65001 and ifset(($php[1]),'/Windows NT[\s\w_-]*(6\.[01])/i')) ? 850 : $var);
   }
  if($cfg['wrap'] == 'auto')	// Auto fehlgeschlagen -> Wrap deaktiviert
   $cfg['wrap'] = 0;
 }

# Char ermitteln
 if(ifset($cfg['char'],'auto')) {
  if(preg_match('/(13)[73]((\1)37)/',date('dnHi'),$var))
   $cfg['char'] = $var[2];
  elseif(ifset($char))
   $cfg['char'] = $char;
  elseif($var = ifset($_SERVER['LANG'],'/(UTF-?8)|((?:iso-)?8859-1)/i') and ($var[1] and !isset($cfg['utf8'])) or ifset($var[2]))	// Linux/Ubuntu
   $cfg['char'] = ($var[1]) ? 'utf8' : 'iso_8859_1';
  elseif(isset($_SERVER['HOME']) and isset($_SERVER['USER']) and isset($_SERVER['TERM']) and isset($_SERVER['SHELL'])	// Unix/Linux/Mac
	and file_exists('/usr/bin/locale') and preg_match('/(utf-?8)|(ansi|iso-?8859-?1|ascii)/i',@exec('locale charmap'),$var))
   $cfg['char'] = (ifset($var[1]) and !isset($cfg['utf8'])) ? 'utf8' : ((ifset($var[2])) ? strtolower(str_replace('-','_',$var[2])) : 'utf7');
  elseif(isset($_SERVER['SystemDrive']) and isset($_SERVER['SystemRoot']) and isset($_SERVER['APPDATA']))	// Windows
   $cfg['char'] = 'oem';
  else
   $cfg['char'] = '7bit';
 }

# Auto-Update (Check)
 if($cfg['upda'] and $uplink and time()-filemtime($script) > $cfg['upda']) {
  dbug("Prüfe auf Updates...");
  if($fbnet = request('GET',"$uplink[1]md5",0,0,$uplink[0],80) and preg_match("/\((\w+)\s([\d.]+)\)/",$fbnet,$var) and floatval($var[2]) > $ver[9])
   out("Ein Update ist verfügbar ($var[1] $var[2]) - Bitte nutzen Sie die Update-Funktion\n\nBeispiel:\n$self info update\n\n");
  else
   @touch($script);
 }

# Parameter auswerten
 if($pset < $pmax and preg_match('/^
	((?<bi>BoxInfo|bi)	|(?<pi>PlugIn|pi)	|(?<lio>Log(in|out)|l[io])	|(?<d>Dial|d)		|(?<e>E(reignisse)?)
	|(?<rc>ReConnect|rc)	|(?<sd>SupportDaten|sd)	|(?<ss>(System)?S(tatu)?s)	|(?<k>K(onfig)?)	|(?<gip>G(et)?IP)
	|(?<i>I(nfo)?|UpGrade|ug)|(?<fb>FooBar|fb)	)$/ix',$argv[$pset],$val)) {	## Modes mit und ohne Login ##
  $pset++;
  dbug('$argv',3);					// Debug Parameter
  dbug($val,3);
  if(ifset($val['bi']) and $val['bi']) {		// Jason Boxinfo
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [BoxInfo|bi]".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self boxinfo
$self 169.254.1.1 bi" : ""));
   elseif($data = request('GET-array','/jason_boxinfo.xml') and preg_match_all('!<j:(\w+)>([^<>]+)</j:\1>!m',$data[1],$array)) {
    dbug($array,4,'BoxInfos');
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
   elseif($data)
    out("Keine BoxInfos erhalten");
   else
    out(errmsg(0,'request'));
  }
  elseif(ifset($val['gip'])) {				// Get Extern IP
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [GetIP|gip] <Filter>".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self getip
$self getip ipv4
$self 169.254.1.1 gip
$self gip dns" : ""));
   elseif($array = getexternalip()) {
    if($pset < $pmax and preg_match('/[-\w]+/',$argv[$pset],$var))
     $array = preg_array("/$var[0]/i",$array,3);
    $val = 0;
    foreach($array as $key => $var)
     $val = max($val,strlen($key));
    foreach($array as $key => $var)
     $array[$key] = str_pad($key,$val," ").": $var";
    out(implode("\n",$array));
   }
   elseif($var = errmsg(0,'getexternalip'))
    out($var);
   else
    out("Keine Externe IP-Adresse verfügbar");
  }
  elseif(ifset($val['i'])) {				// Info (Intern)
   if(ifset($cfg['help']))
    out("$self [Info|i] <Funktion|Datei> <Parameter>\n
Funktionen:
Echo <str> <...>     Parameter ausgeben
Globals              Alle PHP-Variabeln ausgeben
PanGramm             Kodierungstest mit Umlauten und ASCII-Art
PHP                  PHPInfo() ausgeben
UpDate <Check|Force> FB_Tools über das Internet aktualisieren
WebGet   <url>       Datei aus Url herunterladen und HTTP-Header ausgeben
StrfTime <str>       Alle Zeit-Angaben von strftime ausgeben
<Datei>              Verschiedene Hashes von einer Datei berechnen".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self info
$self info echo Hello World
$self info php
$self info fb_config.php
$self info update
$self info wg http://m.p7.de/fb_tools.bat
$self info strftime '%d.%m.%y %H:%M:%S'
$self i pg -c:ansi
$self i -d" : ""));
   elseif($pset < $pmax and preg_match('/^(?:(?<php>PHP)|(?<g>G(?:LOBALS)?)|(?<pg>P(?:anGramm|g))|(?<ud>U(?:pdate|d))|(?<e>e|Echo)|(?<wg>wg|W(eb)?Get)|(?<st>st|StrfTime))$/ix',$argv[$pset],$var)
	or ifset($val['i'],'/upgrade|ug/')) {
    $pset++;
    if(ifset($var['php'])) {				// PHPInfo()
     ob_start();
     phpinfo();
     $data = ob_get_contents();
     ob_clean();
     out($data);
    }
    elseif(ifset($var['g']))				// GLOBALS
     out($GLOBALS);
    elseif(ifset($var['pg']))				// Pangramm mit Kitty
     out(wordwrap("Welch fieser Katzentyp quält da süße Vögel bloß zum Jux?\n",min((($cfg['wrap']) ? (int)$cfg['wrap'] : 80),42)).$cfg['zlib']['inflate'](base64_decode(
	"TY0xDsMwDAP3APkDN9qAaC/ZDOQD/YIRa+2epUCQt1dOl2ohJR4o4H+u/tNhZpLMxrrciTuwe36Sal5E5/QqQFNrk0JFAAlPwWWbEHuWjVJajzuc4roQxwQOOMD4QKZRQyIX+8h4vc/z8wU")));
    elseif(ifset($var['e']))				// Echo
     out(trim(implode(' ',array_slice($argv,$pset,$pmax-$pset))));
    elseif(ifset($var['st'])) {				// strftime
     if($pset < $pmax)
      out(strftime(trim(implode(' ',array_slice($argv,$pset,$pmax-$pset)))));
     else {
      $array = array();
      $len = 0;
      for($a=1; $a <= 26; $a++)		// Alle Buchstaben
       for($b=0; $b < 2; $b++)		// Gross und kleinschreibung
        for($c=0; $c < 2; $c++) {	// Positiv und Negativ
         $key = (($c) ? '-' : "").chr($a + 64 + 32 * $b);
         $var = strftime("%$key");
         $val = str_replace(array("\n","\t"),array('\n','\t'),str_pad("%$key",4," ")."= $var");
         if($var and $var != "%$key" and (!$c or preg_replace('/^%-?(\w)\s*/','%$1  ',$val) != $array[substr($key,1)])) {
          $array[$key] = $val;
          $len = max($len,strlen($val));
         }
        }
//      ksort($array);
      $array = array_values($array);
      $out = array();
      for($a=0; $a < floor(count($array)/2); $a++)
       $out[] = str_pad($array[$a],$len+1," ").$array[$a+floor(count($array)/2)];
      out(implode("\n",$out)."\n\nWeitere Hinweise finden Sie auf strftime.org");
     }
    }
    elseif(ifset($var['wg']) and $pset < $pmax and preg_match('!^(http://)?([.\w-]+)(.*)$!',$argv[$pset],$var))	// WGet
     out(request(array('method' => "GET-save:./", 'host' => $var[2], 'page' => $var[3])));
    elseif(ifset($var['ud']) or ifset($val['i'],'/upgrade|ug/i')) {	// FB_Tools Update
     if($uplink and $fbnet = request('GET-array',"$uplink[1]md5",0,0,$uplink[0],80)) {	// Update-Check
      if(preg_match("/((\d\d)\.(\d\d)\.(\d{4}))\s[\d:]+\s*\((\w+)\s([\d.]+)\)(?:.*?(\w+)\s\*\w+\.$ext(?=\s))?/s",$fbnet[1],$up)) {
       $val = ($pset < $pmax and preg_match('/^(?:(C(?:heck)?)|(F(?:orce)?))$/i',$argv[$pset],$var)) ? $var : false;
       if(intval($up[4].$up[3].$up[2]) > $ver[8] and floatval($up[6]) > $ver[9] or ifset($val[2])) {
        out("Ein Update ist verfügbar: $up[5] $up[6] vom $up[1]");
        if(!ifset($val[1]) or ifset($val[2])) {
         out("Installiere Update ... ");
         $manuell = "!\nBitte installieren Sie es von http://$uplink[0]/.dg manuell!";
         if(ifset($up[7]) and $up[8] = @request('GET',"$uplink[1]$ext.gz",0,0,$uplink[0],80)) {
          $rename = preg_replace('/(?=(\.\w+)?$)/',"_$ver[2].bak",$script,1);	// Neuer Name für alte Version
          if($var = $cfg['zlib']['decode']($up[8]) and md5($var) == $up[7] and @rename($script,$rename)) {	// Update ab PHP5
           file_put_contents($script,$var);
           @chmod($script,intval(fileperms($rename),8));
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
        @touch($script);						// Aktuelles Datum setzen
        out("Kein neues Update verfügbar");
        if(ifset($up[6],$ver[9]) and $up[7] != md5_file($script))	// MD5-Check
         out("Hinweis: $self wurde verändert");
       }
      }
      else								// fb_tools.md5 nicht verfügbar
       out("Update-Server sagt NEIN!");
      if(ifset($fbnet['X-Cookie']))					// Coolen Spruch ausgeben
       out("\n".$fbnet['X-Cookie']);
     }
     else								// Kein Netzwerk/Internet verfügbar
      out("Computer sagt NEIN!");
    }
   }
   elseif($cfg['dbug']) {				// DEBUG: $argv & $cfg ausgeben mit Login-Test
    dbug('$argv');
    $sid = $cfg['sid'] = login();
    dbug('$cfg');
    if($sid)
     logout($sid);
   }
   else {						// FB_Tools-Version, PHP Kurzinfos und Hashes ausgeben
    $var = array("PHP $php[0]/".$php[2],$php[1]);
    out("$ver[0]\n".implode(($cfg['wrap'] and strlen($var[0].$var[1])+3 < $cfg['wrap']) ? " - " : "\n",$var)
	."\nTerminal-Infos: Breite: $cfg[wrap], Zeichensatz: $cfg[char]\n\n");
    $file = ($pset < $pmax and file_exists($argv[$pset])) ? $argv[$pset++] : $script;
    $data = file_get_contents($file);
    $array = array('File' => $file,'Size' => number_format(filesize($file),0,0,'.')." Bytes",'CRC32' => crc_32($data),'MD5' => md5($data),'SHA1' => sha1($data));
    if(function_exists('hash') and $file != $script)
     $array = array_merge($array,array('SHA256' => hash('sha256',$data),'SHA512' => hash('sha512',$data)));
    $max = max(array_map('strlen',array_keys($array)));
    foreach($array as $key => $var)
     out(str_pad("$key:",$max+2,' ').(($cfg['wrap'] and $cfg['wrap'] < strlen($var)+$max+2) ? substr_replace($var,str_pad("\n",$max+3,' '),strlen($var)/2,0) : $var));
    if($cfg['char'] == 'utf7' and isset($cfg['utf8']) and !$cfg['utf8'] and preg_match('/linux/i',$php[1]))
     out("\nHinweis: UTF-8 wird nicht unterstützt, Bitte installieren Sie das Paket php-xml nach\nBeispiel: sudo apt-get install php-xml");
   }
  }
  elseif(ifset($val['rc'])) {				// ReConnect
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [ReConnect|rc]".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self reconnect
$self 169.254.1.1 rc" : ""));
   else
    out(($var = forcetermination()) ? "Reconnect ausgeführt" : errmsg(0,'getexternalip'));
  }
  elseif(ifset($val['ss'])) {				// SystemStatus
   if(ifset($cfg['help']))
    out("$self <fritz.box:port> [SystemStatus|Status|ss] <supportcode>".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self systemstatus
$self 169.254.1.1 status
$self ss \"FRITZ!Box Fon WLAN 7390-B-010203-040506-000000-000000-147902-840522-22574-avm-de\"" : ""));
   else
    out(($var = supportcode(($pset < $pmax) ? $argv[$pset++] : false)) ? $var : errmsg(0,'supportcode'));
  }
  elseif(ifset($val['e'])) {				// Ereignisse
   if(ifset($cfg['help']))
    out("$self <user:pass@fritz.box:port> [Ereignisse|e] <Datei|:> <Filter> <Seperator>\n
Folgende Filter sind Möglich: alle, telefon, internet, usb, wlan, system
Hinweis: Der Dateiname wird mit strftime geparst (http://strftime.org)".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self password@fritz.box Ereignisse event.csv
$self password@fritz.box Ereignisse event-internet.csv internet ;
$self 192.168.178.1 e : -pw:secret
$self user:pass@169.254.1.1 e logs-%y%m%d.log" : ""));
   elseif($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {	// Einloggen
    $file = ($pset == $pmax) ? false : $argv[$pset++];
    if(ifset($file,'/^[:*]$/'))					// * : -> Ausgabe auf Console
     $file = false;
    elseif(strpos($file,'%') !== false)			// Dateiname mit strftime parsen?
     $file = strftime($file);
    $filter = ($pset < $pmax and preg_match('/^(alle|telefon|internet|usb|wlan|system)$/i',$argv[$pset++],$var)) ? strtolower($var[0]) : 'alle';
    $sep = ($pset < $pmax) ? $argv[$pset++] : " ";	// Seperator für CSV-Dateien
    $psep = preg_quote($sep,'/');
    $data = getevent($filter,$sep);			// Ereignisse holen
    if($file and file_exists($file) and $fp = fopen($file,'a+')) {// Ereignisse mit vorhandener Datei Syncronisieren
     dbug("Syncronisiere Ereignisse");
     fseek($fp,-256,SEEK_END);	// Die letzten 256 Bytes lesen
     if(preg_match("/(\d\d)\.(\d\d)\.(?:20)?(\d\d)$psep([\d:]+)$psep(.*)\s*\$/",fread($fp,256),$last)) {	// Letzten Eintrag holen
      $date = strtotime("20$last[3]-$last[2]-$last[1] $last[4]");	// Datum vom Letzten Eintrag
      $array = explode("\r\n",$data);
      $data = array();
      foreach($array as $line)
       if(preg_match("/^(\d\d)\.(\d\d)\.(\d\d)$psep([\d:]+)$psep/",$line,$var) and strtotime("20$var[3]-$var[2]-$var[1] $var[4]") > $date)
        $data[] = $line;
      fwrite($fp,implode("\r\n",$data)."\r\n");
      out(($var = count($data)) ? (($var == 1) ? "Ein neues Ereignis wurde" : "$var neue Ereignisse wurden")." gespeichert" : "Keine neuen Ereignisse erhalten");
     }
     else
      out("Datei ist keine Ereignis Logdatei");
     fclose($fp);
    }
    elseif($file)					// Neue Datei anlegen
     out((file_put_contents($file,$data)) ? (($var = substr_count($data,"\n")) ? (($var == 1) ? "Ein Ereignis wurde" : "$var Ereignisse wurden")." gespeichert"
     : errmsg(0,'getevent')) : "$file konnte nicht angelegt werden");
    elseif(preg_match_all("/^([\d.]+{$psep}[\d:]+)$psep(.*?)\s*$/m",$data,$array))	// Ereignisse ausgeben
     foreach($array[0] as $key => $var)
      out(wordwrap(trim($var),$cfg['wrap']-2,str_pad("\n",strlen($array[1][$key])+2," "),true));
    else
     out(errmsg(0,'getevent'));
    if(!ifset($cfg['bsid']))						// Ausloggen
     logout($sid);
   }
   else
    out(errmsg(0,'login'));
  }
  elseif(ifset($val['k'])) {				// Konfig
   if(ifset($cfg['help']) or $pset == $pmax)
    out("$self <user:pass@fritz.box:port> [Konfig|k] [Funktion] <Datei|Ordner> <Kennwort>\n
Funktionen:
ExPort          <Datei>  <Kennwort> - Konfig exportieren(1)
ExPort-DeCrypt  <Datei>  <Kennwort> - Konfig entschlüsseln und exportieren(1,3)
ExTrakt         <Ordner> <Kennwort> - Konfig entpackt anzeigen/exportieren(1)
ExTrakt-DeCrypt <Ordner> <Kennwort> - Konfig entpackt entschl./anz./exp.(1,3)
File            [Datei]  <Ordner> - Konfig aus Datei entpacken und anzeigen(2)
File            [Ordner] [File]   - Konfig-Ordner in Datei zusammenpacken(2)
File-CalcSum    [Ordner] [File]   - Veränderter Konfig-Ordner Zusammensetzen(2)
File-JSON       [Datei] [Datei]   - Konfig-Daten in JSON konvertieren(2)
File-DeCrypt    [Datei] [Kennwort] <Datei> - Konfig-Daten entschlüsseln(1,3)
ImPort          [Datei|Ordner] <Kennwort>  - Konfig importieren(1)
ImPort-CalcSum  [Datei|Ordner] <Kennwort>  - Veränderte Konfig importieren(1)

(1) Anmeldung mit Logindaten erforderlich / (2) Ohne Fritz!Box nutzbar
(3) Fritz!Box mit OS 5 oder neuer erforderlich / [ ] Pflicht / < > Optional
Ab OS 6.69 muss die Sicherheits-Bestätigungsfunktion deaktiviert werden".((preg_match('/[ab]/i',$cfg['help'])) ? "\n

Beispiele:
$self password@fritz.box konfig export
$self fritz.box konfig extrakt
$self konfig file fritzbox.export.gz
$self fritz.box konfig file-decrypt fb.export geheim fbdc.export -d
$self fritz.box konfig extract archiv.tar.gz
$self k fcs Export-Ordner fritzbox.export
$self username:password@fritz.box konfig import \"fb 7170.export\"
$self password@fritz.box konfig import archiv.tar.gz
$self 169.254.1.1 k ipcs \"FRITZ.Box Fon WLAN 6360 85.04.86_01.01.00_0100.export\"" : ""));
   elseif(preg_match('/^(						# 1:Alle
	|i(p|mport)(cs|-calcsum)?					# 2:Import 3:CalcSum
	|e(p|xport)(?:(dc|-de(?:crypt|code))?)				# 4:Export 5:DeCrypt
	|(et|(?:extra[ck]t))?(?:(dc|-de(?:crypt|code))?)		# 6:Extrakt 7:DeCrypt
	|(f(?:ile)?)(?:(cs|-calcsum)?|(dc|-de(?:crypt|code))?|(-?json)?)# 8:File 9:CalcSum 10:DeCrypt 11:JSON
		)$/ix',$argv[$pset++],$mode)) {
    dbug($mode,3);					// Debug Parameter
    $mode = array_pad($mode,12,null);
    $file = ($pset < $pmax) ? $argv[$pset++] : false;
    $pass = ($pset < $pmax) ? $argv[$pset++] : false;
    if(isset($cfg['utf8']) and !$cfg['utf8'] and ($mode[3] or $mode[5] or $mode[10]))
     out("Hinweis: UTF8 wird auf Ihrem System von PHP nicht ünterstützt, daher ist die Entschlüsselung Problematisch!");
    if(($mode[2] or $mode[4] or $mode[6])) {		// Login Optionen
     if($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
      if($mode[5] or $mode[7]) {			// Kennwort-Entschlüsselung
       if($cfg['fiwa'] > 500) {
        if(!$pass)					// Im DeKode-Modus kein leeres Kennwort zulassen
         $pass = (ifset($cfg['pass'],'/^[ -~]+$/')) ? $cfg['pass'] : 'geheim';
       }
       else {
        out("Entschlüsselung wird nicht unterstützt");
        $mode[5] = $mode[7] = false;
       }
      }
      if($mode[4]) {					// Export
       if(is_dir($file)) {				// Im Ordner schreiben
        makedir($file);					// Verzeichnis erstellen
        $file = false;
       }
       if($mode[5] and $pass and $data = cfgexport('array',$pass) and $data[1]) {	// Exportieren mit Entschlüsselten Benutzerdaten
        if($data[1] and $data[1] = konfigdecrypt($data[1],$pass,$sid)) {
         out(showaccessdata($data[1]));
         saverpdata($file,$data,'file.export');
        }
        else
         out(errmsg(0,'konfigdecrypt') || errmsg(0,'cfgexdport') || errmsg(0,'request'));
       }
       elseif(!ifset($data))				// Export direkt File
        out(cfgexport(($file) ? $file : true,$pass) ? "Konfiguation wurde erfolgreich exportiert" : errmsg(0,'request'));
       else
        out(($var = errmsg(0,'request')) ? $var : "Keine Konfig erhalten - Möglichlichweise ist noch die Sicherheits-Bestätigungsfunktion aktiviert?");
      }
      elseif($mode[6]) {					// Extrakt
       if($data = cfgexport('array',$pass) and $data[1]) {	// Konfigdaten holen
        $mod = ($file) ? ((preg_match($cfg['ptar'],$file,$var)) ? ((ifset($var[1])) ? 3 : 2) : 1) : 0;
        if($mod == 1)
         makedir($file);					// Verzeichnis erstellen
        if($mode[7] and $pass and $data[2] = konfigdecrypt($data[1],$pass,$sid))	// Konfig Entschlüsseln
         out(cfginfo($data[2],$mod,$file,showaccessdata($data[2])));
        else
         out(cfginfo($data[1],$mod,$file));
       }
       else
        out(($var = errmsg(0,'request')) ? $var : "Keine Konfig erhalten - Möglichlichweise ist noch die Sicherheits-Bestätigungsfunktion aktiviert?");
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
    elseif($mode[8] and !$mode[10] and !$mode[11] and is_file($file) and $data = file_read($file) and preg_match('/^\*{4}\s+\w+.*CONFIGURATION EXPORT/',$data)) {	// Converter-Modus File -> Dir
     $mod = ($pass) ? ((preg_match($cfg['ptar'],$pass,$var)) ? ((ifset($var[1])) ? 3 : 2) : 1) : 0;
     if($mod == 1)	// Verzeichniss angegeben ?
      makedir($pass);
     out(($data = cfginfo($data,$mod,$pass)) ? $data : "Keine Konfig Export-Datei angegeben");
    }
    elseif($mode[8] and !$mode[10] and !$mode[11] and (preg_match($cfg['ptar'],$file,$val) and is_file($file) or is_dir($file))) {	// Converter-Modus Dir/Tar -> File
     if($val)
      $file = tar2array($file);
     out(($file) ? (($data = cfgmake($file,$mode[9],$pass)) ? (($pass) ? $data : cfginfo($data)) : "Kein Konfig Export-Archiv/Verzeichnis angegeben") : errmsg(0,'tar2array'));
    }
    elseif($mode[8] and $mode[10] and !$mode[11] and $pass and is_file($file) and $data = (preg_match($cfg['ptar'],$file)) ? cfgmake(tar2array($file)) : file_read($file)) {		// Kennwörter Entschlüsseln
     if($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
      if($cfg['fiwa'] > 500) {					// Entschlüsselung durchführen
       if($data = konfigdecrypt($data,$pass,$sid)) {
        $save = false;
        if($pset < $pmax and $save = $argv[$pset++]) {
         if(preg_match('/^(.*?)[\\\\\/]$/',$save,$var)) {	// Verzeichnis erstellen
          makedir($save);
          $mod = 1;
         }
         elseif(preg_match($cfg['ptar'],$save,$var))
          $mod = (ifset($var[1])) ? 3 : 2;
         else {
          file_write($save,$data);				// Entschlüsselte Konfig sichern
          $mod = 0;
         }
        }
        else
         $mod = 0;
        out(cfginfo($data,$mod,$save,showaccessdata($data)));	// Daten als Text Präsentieren
       }
       else {
        out(errmsg(0,'konfigdecrypt'));
        if(!ifset($pass,'/^[ -~]+$/'))
         out("Hinweis: Das Konfig-Kennwort enthält Sonderzeichen, die bei unterschiedlicher Zeichenkodierung Probleme bereiten können");
       }
      }
      else
       out("Entschlüsselung wird nicht unterstützt");
      if(!ifset($cfg['bsid']))
       logout($sid);
     }
     else
      out(errmsg(0,'login'));
    }
    elseif($mode[8] and $mode[11] and is_file($file) and $pass)				// JSON Konverter (File -> File)
     if(function_exists('json_encode'))
      if($data = file_read($file) and $array = konfig2array($data))
       if($data = json_encode($array) and file_write($pass,$data))
        out("Konfig-Datei erflogreich in JSON konvertiert");
       else
        out("JSON_Encode von PHP $php[0] ist fehlgeschlagen");
      else
       out("Keine Konfig-Datei");
     else
      out("JSON wird von PHP $php[0] nicht unterstützt");
    else
     out("Parameter-Ressourcen zu Konfig $mode[0] nicht gefunden oder nicht korrekt angegeben");
   }
   else
    out("Unbekannte Funktionsangabe für Konfig");
  }
  elseif(ifset($val['d'])) {				// Dial
   if(ifset($cfg['help']) or $pset == $pmax)
    out("$self <user:pass@fritz.box:port> [Dial|d] [Rufnummer] <Telefon>\n
Telefon:
1-4 -> FON 1-4 | 50 -> ISDN/DECT | 51-58 -> ISDN 1-8 | 60-65 -> DECT 1-6".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self password@fritz.box dial 0123456789 50
$self username:password@fritz.box dial \"#96*7*\"
$self 169.254.1.1 d - -pw:geheim" : ""));
   elseif($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
    out((dial($argv[$pset++],(($pset < $pmax) ? $argv[$pset++] : false))) ? "Rufnummer wurde gewählt" : errmsg(0,'dial'));
    if(!ifset($cfg['bsid']))
     logout($sid);
   }
   else
    out(errmsg(0,'login'));
  }
  elseif(ifset($val['sd'])) {				// Supportdaten
   if(ifset($cfg['help']))
    out("$self <user:pass@fritz.box:port> [SupportDaten|sd] <Datei|Ordner|.> <ExTrakt> <TeleMetrie:(an|aus)>".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self password@fritz.box supportdaten support.txt
$self password@fritz.box supportdaten . telemetrie
$self password@fritz.box supportdaten sd-ordner extrakt -d
$self 169.254.1.1 sd -pw:geheim" : ""));
   elseif($sid = (ifset($cfg['bsid'])) ? $cfg['bsid'] : login()) {
    $file = ($pset < $pmax) ? $argv[$pset++] : false;
    $et = ($pset < $pmax and ifset($argv[$pset],'/^(ExTrakt|et)$/i')) ? $pset++ : false;
    $tm = ($pset < $pmax and $var = ifset($argv[$pset],'/^(Telemetrie|tm)([:=-]a((n)|us))$/i')) ? ((ifset($var[4])) ? $pset++ : 0) : false;
    $mode = ($file and preg_match($cfg['ptar'],$file,$var)) ? ((ifset($var[1])) ? 2 : 1) : 0;
    if(!$mode and $et and $file and makedir($file))	// Neues Verzeichniss erstellen
     $file = './';
    if($et and $cfg['fiwa'] >= 630) {		// Extrakt
     dbug("Hole Support-Daten zum extrahieren");
     if($data = supportdata(0,$tm) and $text = supportdataextrakt($data[1],$mode,$file))
      out("\n$text\n");
     elseif($data[1])
      file_write((!preg_match($cfg['ptar'],$file) and substr($file,-1) != '/') ? $file : ((preg_match('/filename=(["\']?)(.*?)\1/i',$data['Content-Disposition'],$var))
       ? preg_replace('/[?\\\\\/<*>:"]+/','_',$var[2]) : "Supportdaten.txt"),$data[1]);
    }
    elseif(supportdata(($file) ? $file : './',$tm))
     out("Supportdaten wurden erfolgreich gespeichert");
    else
     out(errmsg(0,'supportdata'));
    if(!ifset($cfg['bsid']))
     logout($sid);
   }
   else
    out(errmsg(0,'login'));
  }
  elseif(ifset($val['lio'])) {				// Manuelles Login / Logout
   if(ifset($cfg['help']))
    out("$self <user:pass@fritz.box:port> [LogIn|LogOut|li|lo] <-s:sid>".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self password@fritz.box login > sid.txt
$self fritz.box login -pw:password -o:sid.txt
$self fritz.box logout -s:sid.txt
$self fritz.box logout -s:0123456789abcdef" : ""));
   elseif(preg_match('/l(?:og)?(?:(in?)|(o(?:ut)))/i',$val['lio'],$var)) {
    if(ifset($var[1]))
     out(login());
    elseif(ifset($var[2]))
     logout($cfg['sid']);
   }
  }
  elseif(ifset($val['pi'])) {				// Plugin
   if($pset < $pmax and file_exists($argv[$pset]) and is_file($argv[$pset]))
    include $argv[$pset++];
   else {
    $dir = ($pset < $pmax and file_exists($argv[$pset]) and is_dir($argv[$pset])) ? preg_replace('![\\\\/]+$!','',$argv[$pset]) : ".";
    if($array = glob("$dir/fbtp_*.php"))
     out("Vorhandene Plugins:\n ".implode("\n ",$array)."\n\n");
    out("$self <user:pass@fritz.box:port> [PlugIn|pi] [Script-Datei] <...>".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self password@fritz.box plugin fbtp_led.php off
$self fritz.box plugin fbtp_test.php" : "")."\n
WARNUNG: Es gibt KEINE Prüfung auf Malware!");
   }
  }
  else
   out("Möglichweise ist ein unerwarterer und unbekannter, sowie mysteriöser Fehler aufgetreten :-)");
 }
 elseif(ifset($cfg['dbug'])) {				// DEBUG: $argv & $cfg ausgeben
  dbug('$argv');
  dbug('$cfg');
 }
 else {							// Hilfe ausgeben
  $help = out("$self <user:pass@fritz.box:port> [mode] <parameter> ... <option>".((ifset($cfg['help'])) ? "\n
Modes:
BoxInfo      - Modell, Firmware-Version und MAC-Adresse ausgeben
Dial         - Rufnummer wählen(2)
Ereignisse   - Systemmeldungen abrufen(2)
GetIP        - Aktuelle externe IPv4-Adresse ausgeben(1)
Info         - FB-Tools/PHP Version, MD5/SHA1 Checksum, Update/Prüfen(3)
Konfig       - Einstellungen Ex/Importieren, Daten entschlüsseln(2,3,4)
Login/Logout - Manuelles Einloggen für Scriptdateien(2)
PlugIn       - Weitere Funktion per Plugin-Script einbinden
ReConnect    - Neueinwahl ins Internet(1)
SupportDaten - AVM-Supportdaten Speichern(2)
SystemStatus - Modell, Version, Laufzeiten, Neustarts und Status ausgeben(3)

(1) Aktiviertes UPnP erforderlich / (2) Anmeldung mit Logindaten erforderlich
(3) Teilweise ohne Fritz!Box nutzbar / [ ] Pflicht / < > Optional
(4) Ab OS 6.69 muss die Sicherheits-Bestätigungsfunktion deaktiviert werden".((preg_match('/[ab]/i',$cfg['help'])) ? "\n
Beispiele:
$self secret@fritz.box supportdaten
$self hans:geheim@fritz.box konfig export
$self secret@169.254.1.1 Ereignisse * -w:80-c:utf8-o:file.txt
$self dial \"**600\" -fb:fritz.box -un:max -pw:geheim
$self fritz.box plugin fbtp_led.php off -pw:secret
$self -h:alles": "") : "\n\nWeitere Hilfe bekommen Sie mit der -h Option oder mehr Hilfe mit -h:all"));
 }
 if($cfg['help']) {					// Weitere Hilfe ausgeben
  if(preg_match('/[ao]/i',$cfg['help']))		// Optionen ausgeben
   out("
Alle Optionen:           (Müssen immer als Letztes angegeben werden!)
         -d             - Debuginfos
         -h:<a|b|o|s>   - Hilfe (Alles, Beispiele, Optionen, Standard)
Console: -c:[CodePage]  - Kodierung der Umlaute ($cfg[char])
         -w:[Breite]    - Wortumbruch ($cfg[wrap])
         -o:[Datei]     - Ansi-Ausgabe in Datei
Request: -b:[Bytes]     - Buffergröße ($cfg[sbuf])
         -t:[Sekunden]  - TCP/IP Timeout ($cfg[tout])
Login:   -s:[SID|Datei] - Manuelle SID Angabe (Für Scriptdateien)
         -fb:[Host]     - Alternative Fritz!Box Angabe ($cfg[host])
         -fw:[Version]  - Manuelle Angabe der Firmware-Version ($cfg[fiwa])
         -pt:[Port]     - Alternative Port Angabe ($cfg[port])
         -pw:[Pass]     - Alternative Kennwort Angabe
         -un:[User]     - Alternative Benutzername Angabe
Dateien: -gz:[Level]    - Packstufe festlegen ({$cfg['zlib']['mode']})");
  elseif($cfg['help'] === true)
   out("\nMehr Hilfe bekommen Sie mit -h:a (Alles) -h:b (Beispiele) -h:o (Optionen)");
  if(ifset($help))
   out("Eine Anleitung finden Sie auf $ver[7]/.dg");
 }
 if($cfg['dbug'] and ifset($cfg['error']))		// Fehler bei -d ausgeben
  dbug("Fehler:\n".print_r($cfg['error'],true));
}

?>
